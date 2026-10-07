<?php

namespace App\Domain\Media\Support;

use App\Domain\Media\Enums\MediaFailureReason;
use App\Domain\Media\Exceptions\MediaRejected;
use Illuminate\Support\Facades\Process;
use RuntimeException;

/**
 * Runs ffprobe and ffmpeg on the media worker (specs/10 §6). Every command is an argument array,
 * never a shell string, and every path is one the job made in its own temp dir (specs/11
 * Injection). Inputs are read with the mp4/mov demuxer only and from local files only, so a file
 * dressed as something else cannot pull in a playlist or another URL, and only allowlisted
 * decoders may run on them.
 */
class Ffmpeg
{
    /**
     * @throws MediaRejected when ffprobe cannot read the file
     */
    public function probe(string $path): VideoProbe
    {
        $result = Process::timeout((int) config('media.video.probe_timeout'))->run($this->probeArguments($path));

        if (! $result->successful()) {
            throw MediaRejected::because(MediaFailureReason::Undecodable, 'ffprobe: '.mb_substr(trim($result->errorOutput()), 0, 500));
        }

        return VideoProbe::fromJson($result->output());
    }

    public function transcode(string $input, string $output, VideoProbe $probe, int $width, int $height, int $crf): void
    {
        $this->run($this->transcodeArguments($input, $output, $probe, $width, $height, $crf), 'transcode', (int) config('media.video.transcode_timeout'));
    }

    public function poster(string $video, string $output, float $at): void
    {
        $this->run($this->posterArguments($video, $output, $at), 'poster', (int) config('media.video.poster_timeout'));
    }

    /**
     * Headers only (`-nofind_stream_info`): no decoder runs on the upload before its codecs are
     * checked against the allowlist.
     *
     * @return list<string>
     */
    public function probeArguments(string $path): array
    {
        return [
            (string) config('media.video.ffprobe'),
            '-v', 'error',
            '-nofind_stream_info',
            ...$this->inputOptions(),
            '-i', $path,
            '-print_format', 'json',
            '-show_format',
            '-show_streams',
        ];
    }

    /**
     * h264 at the configured preset and CRF, scaled to the given size (rotation is applied before
     * the filter), the first audio track only as aac, everything else (subtitles, data, chapters,
     * metadata) dropped, and the index up front for progressive playback (specs/10 §6, 23 §4).
     *
     * @return list<string>
     */
    public function transcodeArguments(string $input, string $output, VideoProbe $probe, int $width, int $height, int $crf): array
    {
        $audio = $probe->audioIndex === null
            ? ['-an']
            : [
                '-map', "0:{$probe->audioIndex}",
                '-c:a', 'aac',
                '-b:a', (string) config('media.video.audio_bitrate'),
                '-ac', (string) min($probe->audioChannels ?? 2, (int) config('media.video.audio_channels_max')),
            ];

        return [
            ...$this->prefix(),
            ...$this->decoders('decoders'),
            ...$this->inputOptions(),
            '-i', $input,
            '-map', "0:{$probe->videoIndex}",
            ...$audio,
            // A file that lied about its duration still stops here (and is refused on the output
            // probe); a pass that would overshoot the size cap stops here too.
            '-t', (string) ((int) config('media.video.max_duration') + 1),
            '-fs', (string) ((int) config('media.video.max_output_bytes') + 1),
            '-fpsmax', (string) config('media.video.output_max_fps'),
            '-vf', "scale={$width}:{$height},format=yuv420p",
            '-c:v', 'libx264',
            '-preset', (string) config('media.video.preset'),
            '-crf', (string) $crf,
            '-sn', '-dn',
            '-map_metadata', '-1',
            '-map_chapters', '-1',
            '-movflags', '+faststart',
            '-f', 'mp4',
            $output,
        ];
    }

    /**
     * @return list<string>
     */
    public function posterArguments(string $video, string $output, float $at): array
    {
        return [
            ...$this->prefix(),
            '-ss', sprintf('%.3F', max(0.0, $at)),
            ...$this->decoders('output_decoders'),
            ...$this->inputOptions(),
            '-i', $video,
            '-frames:v', '1',
            '-an', '-sn', '-dn',
            '-map_metadata', '-1',
            '-c:v', 'libwebp',
            '-quality', (string) config('media.video.poster_quality'),
            '-f', 'webp',
            $output,
        ];
    }

    /**
     * @return list<string>
     */
    private function prefix(): array
    {
        return [
            'nice', '-n', (string) config('media.video.nice'),
            (string) config('media.video.ffmpeg'),
            '-nostdin',
            '-hide_banner',
            '-loglevel', 'error',
            '-y',
            '-timelimit', (string) config('media.video.ffmpeg_timelimit'),
        ];
    }

    /**
     * Local files only, the mp4/mov demuxer only, and no external data references or absolute
     * paths inside the file, set explicitly rather than trusted to the package defaults.
     *
     * @return list<string>
     */
    private function inputOptions(): array
    {
        return ['-protocol_whitelist', 'file', '-enable_drefs', '0', '-use_absolute_path', '0', '-f', 'mov'];
    }

    /**
     * @return list<string>
     */
    private function decoders(string $key): array
    {
        /** @var list<string> $names */
        $names = config("media.video.{$key}");

        return ['-codec_whitelist', implode(',', $names)];
    }

    /**
     * A failed run is retried by the job (`processing_error` once retries are spent): it can be
     * the worker as much as the file.
     *
     * @param  list<string>  $arguments
     */
    private function run(array $arguments, string $step, int $timeout): void
    {
        $result = Process::timeout($timeout)->run($arguments);

        if (! $result->successful()) {
            throw new RuntimeException("ffmpeg {$step} failed ({$result->exitCode()}): ".mb_substr(trim($result->errorOutput()), 0, 500));
        }
    }
}
