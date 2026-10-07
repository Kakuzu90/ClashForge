<?php

namespace App\Domain\Media\Support;

use App\Domain\Media\Enums\MediaFailureReason;
use App\Domain\Media\Exceptions\MediaRejected;

/**
 * The video limits checked on the probe, before any transcode (specs/10 §4, §6), and the output
 * size. Limits come from `media.video`.
 */
final class VideoRules
{
    /**
     * @throws MediaRejected
     */
    public static function check(VideoProbe $probe): void
    {
        /** @var list<string> $videoCodecs */
        $videoCodecs = config('media.video.video_codecs');
        /** @var list<string> $audioCodecs */
        $audioCodecs = config('media.video.audio_codecs');

        if (! in_array($probe->videoCodec, $videoCodecs, true) || ($probe->audioCodec !== null && ! in_array($probe->audioCodec, $audioCodecs, true))) {
            throw MediaRejected::because(MediaFailureReason::UnsupportedCodec, "{$probe->videoCodec}/".($probe->audioCodec ?? 'none'));
        }

        if ($probe->width <= 0 || $probe->height <= 0) {
            throw MediaRejected::because(MediaFailureReason::Undecodable, "size {$probe->width}x{$probe->height}");
        }

        self::checkDuration($probe);

        // Decoding cost grows with frames, not just pixels: 4K at 240 fps would hold the worker.
        if ($probe->frameRate > (float) config('media.video.max_frame_rate')) {
            throw MediaRejected::because(MediaFailureReason::FrameRateTooHigh, "{$probe->frameRate} fps");
        }

        $long = max($probe->width, $probe->height);
        $short = min($probe->width, $probe->height);

        if ($long > (int) config('media.video.max_long_side') || $short > (int) config('media.video.max_short_side')) {
            throw MediaRejected::because(MediaFailureReason::TooLarge, "{$probe->width}x{$probe->height}");
        }
    }

    /**
     * Run on the input and again on the output: a container can claim a shorter length than its
     * streams have, and such a file is refused, never published cut (specs/10 §4).
     *
     * @throws MediaRejected
     */
    public static function checkDuration(VideoProbe $probe): void
    {
        // To a tenth of a second, so a clip the phone timed at 60.04 s still counts as 60.
        if (round($probe->duration, 1) > (float) config('media.video.max_duration')) {
            throw MediaRejected::because(MediaFailureReason::TooLong, "{$probe->duration} s");
        }
    }

    /**
     * The short side brought down to `output_short_side`, never up, aspect kept, both sides even
     * as h264 with 4:2:0 chroma needs.
     *
     * @return array{int, int}
     */
    public static function outputSize(int $width, int $height): array
    {
        $short = min($width, $height);
        $scale = min(1.0, (int) config('media.video.output_short_side') / $short);

        $even = fn (float $side): int => max(2, (int) round($side) - ((int) round($side) % 2));

        return [$even($width * $scale), $even($height * $scale)];
    }
}
