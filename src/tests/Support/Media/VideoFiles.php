<?php

namespace Tests\Support\Media;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use PHPUnit\Framework\SkippedWithMessageException;
use RuntimeException;

/**
 * Builds small videos for pipeline tests with ffmpeg's test sources, so no binary fixtures live in
 * the repo. Tests skip where ffmpeg is missing (it ships in the app and media images).
 */
final class VideoFiles
{
    /**
     * @param  array{
     *     width?: int, height?: int, seconds?: float, rate?: int,
     *     video?: string, audio?: list<string>, subtitle?: bool, rotate?: int,
     *     metadata?: array<string, string>, streamMetadata?: array<string, array<string, string>>, format?: string,
     * }  $spec  `audio` lists one encoder per track (empty: no audio)
     */
    public static function make(array $spec = []): string
    {
        self::requireFfmpeg();

        $width = $spec['width'] ?? 640;
        $height = $spec['height'] ?? 360;
        $seconds = $spec['seconds'] ?? 2;
        $rate = $spec['rate'] ?? 10;
        $audio = $spec['audio'] ?? ['aac'];

        $dir = sys_get_temp_dir().'/clashcommons-video-fixtures/'.getmypid();
        File::ensureDirectoryExists($dir);
        $out = $dir.'/'.bin2hex(random_bytes(6));

        $args = ['ffmpeg', '-v', 'error', '-y', '-f', 'lavfi', '-i', "testsrc=size={$width}x{$height}:rate={$rate}:duration={$seconds}"];
        foreach ($audio as $i => $encoder) {
            $args = [...$args, '-f', 'lavfi', '-i', 'sine=frequency='.(440 * ($i + 1)).":duration={$seconds}"];
        }
        if ($spec['subtitle'] ?? false) {
            $srt = $out.'.srt';
            file_put_contents($srt, "1\n00:00:00,000 --> 00:00:01,000\nHello\n");
            $args = [...$args, '-i', $srt];
        }

        $args = [...$args, '-map', '0:v'];
        foreach (array_keys($audio) as $i) {
            $args = [...$args, '-map', ($i + 1).':a'];
        }
        if ($spec['subtitle'] ?? false) {
            $args = [...$args, '-map', (count($audio) + 1).':s', '-c:s', 'mov_text'];
        }

        $args = [...$args, '-c:v', $spec['video'] ?? 'libx264', '-preset', 'ultrafast', '-pix_fmt', 'yuv420p'];
        foreach ($audio as $i => $encoder) {
            $args = [...$args, "-c:a:{$i}", $encoder];
        }
        foreach ($spec['metadata'] ?? [] as $key => $value) {
            $args = [...$args, '-metadata', "{$key}={$value}"];
        }
        foreach ($spec['streamMetadata'] ?? [] as $stream => $tags) {
            foreach ($tags as $key => $value) {
                $args = [...$args, "-metadata:{$stream}", "{$key}={$value}"];
            }
        }
        $args = [...$args, '-t', (string) $seconds, '-f', $spec['format'] ?? 'mp4', $out];

        self::run($args);

        if (isset($spec['rotate'])) {
            $rotated = $out.'-r';
            self::run(['ffmpeg', '-v', 'error', '-y', '-display_rotation:v:0', (string) $spec['rotate'], '-i', $out, '-c', 'copy', '-f', 'mp4', $rotated]);
            $out = $rotated;
        }

        return (string) file_get_contents($out);
    }

    /**
     * Streams of a stored rendition: codec types in order, the video size and the format tags.
     *
     * @return array{streams: list<string>, width: int, height: int, tags: array<string, string>, streamTags: list<array<string, string>>}
     */
    public static function describe(string $bytes): array
    {
        $path = sys_get_temp_dir().'/clashcommons-video-fixtures/'.getmypid().'/probe-'.bin2hex(random_bytes(6));
        File::ensureDirectoryExists(dirname($path));
        file_put_contents($path, $bytes);

        $result = Process::run(['ffprobe', '-v', 'error', '-print_format', 'json', '-show_format', '-show_streams', $path]);
        /** @var array{streams: list<array<string, mixed>>, format: array<string, mixed>} $data */
        $data = json_decode($result->output(), true);
        $video = array_values(array_filter($data['streams'], fn (array $s): bool => $s['codec_type'] === 'video'))[0];

        return [
            'streams' => array_map(fn (array $s): string => $s['codec_type'].':'.$s['codec_name'], $data['streams']),
            'width' => (int) $video['width'],
            'height' => (int) $video['height'],
            'tags' => $data['format']['tags'] ?? [],
            'streamTags' => array_map(fn (array $s): array => $s['tags'] ?? [], $data['streams']),
        ];
    }

    public static function requireFfmpeg(): void
    {
        if (! Process::run(['ffmpeg', '-version'])->successful()) {
            throw new SkippedWithMessageException('ffmpeg is not installed here.');
        }
    }

    /**
     * @param  list<string>  $args
     */
    private static function run(array $args): void
    {
        $result = Process::timeout(120)->run($args);

        if (! $result->successful()) {
            throw new RuntimeException('fixture ffmpeg failed: '.$result->errorOutput());
        }
    }
}
