<?php

namespace App\Domain\Media\Support;

use App\Domain\Media\Contracts\MediaProcessor;
use App\Domain\Media\Enums\MediaCollection;
use App\Domain\Media\Enums\MediaFailureReason;
use App\Domain\Media\Enums\VariantName;
use App\Domain\Media\Exceptions\MediaRejected;
use RuntimeException;

/**
 * Replay videos: signature → probe → duration, codec and size limits → h264/aac transcode (a
 * second, stronger pass when over the output cap) → WebP poster (specs/10 §3, §4, §6). The
 * re-encode is the polyglot defence, so there is no byte scan for script markers as for images:
 * across 100 MB of compressed video they would match by chance. Work files go next to the
 * original, in the job's temp dir.
 */
final class VideoProcessor implements MediaProcessor
{
    public function __construct(private readonly Ffmpeg $ffmpeg) {}

    public function process(string $localPath, MediaCollection $collection): ProcessedMedia
    {
        $mimeType = FileInspector::mimeType($localPath);

        /** @var array<string, string> $allowed */
        $allowed = config('media.video.real_mimes');

        // Declared as an mp4 but something else underneath.
        if (! array_key_exists($mimeType, $allowed)) {
            throw MediaRejected::suspicious("real MIME {$mimeType}");
        }

        $probe = $this->ffmpeg->probe($localPath);
        VideoRules::check($probe);

        $dir = dirname($localPath);
        $video = $this->transcode($localPath, "{$dir}/video.mp4", $probe);
        $output = $this->probeOutput($video);
        VideoRules::checkDuration($output);

        $poster = "{$dir}/poster.webp";
        $this->ffmpeg->poster($video, $poster, $output->duration * (float) config('media.video.poster_at'));

        return new ProcessedMedia(
            mimeType: $mimeType,
            extension: $allowed[$mimeType],
            width: $output->width,
            height: $output->height,
            variants: [
                ProcessedVariant::fromFile(VariantName::Video720p, $video, 'video/mp4', 'mp4', $output->width, $output->height),
                ProcessedVariant::fromFile(VariantName::Poster, $poster, 'image/webp', 'webp', $output->width, $output->height),
            ],
            durationSeconds: round($output->duration, 2),
        );
    }

    /**
     * Our own output unreadable is a worker fault, retried like a failed transcode, not the
     * uploader's `undecodable`.
     */
    private function probeOutput(string $video): VideoProbe
    {
        try {
            return $this->ffmpeg->probe($video);
        } catch (MediaRejected $e) {
            throw new RuntimeException("unreadable transcode output: {$e->detail}", previous: $e);
        }
    }

    private function transcode(string $input, string $output, VideoProbe $probe): string
    {
        [$width, $height] = VideoRules::outputSize($probe->width, $probe->height);
        $max = (int) config('media.video.max_output_bytes');

        foreach ([(int) config('media.video.crf'), (int) config('media.video.crf_retry')] as $crf) {
            $this->ffmpeg->transcode($input, $output, $probe, $width, $height, $crf);
            clearstatcache(true, $output);

            if ((int) filesize($output) <= $max) {
                return $output;
            }
        }

        throw MediaRejected::because(MediaFailureReason::OutputTooLarge, filesize($output).' bytes');
    }
}
