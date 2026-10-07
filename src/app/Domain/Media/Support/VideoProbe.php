<?php

namespace App\Domain\Media\Support;

use App\Domain\Media\Enums\MediaFailureReason;
use App\Domain\Media\Exceptions\MediaRejected;
use JsonException;

/**
 * What `ffprobe` reads from the container headers (specs/10 §6 "Probe"): the first real video
 * stream (cover art is skipped), the first audio stream, the duration, the frame rate and the size
 * as displayed, rotation applied. A size of 0 means the header did not say; VideoRules refuses it
 * after the codec check.
 */
final readonly class VideoProbe
{
    public function __construct(
        public float $duration,
        public int $width,
        public int $height,
        public float $frameRate,
        public int $videoIndex,
        public string $videoCodec,
        public ?int $audioIndex,
        public ?string $audioCodec,
        public ?int $audioChannels,
    ) {}

    /**
     * @throws MediaRejected when the output does not describe a playable video
     */
    public static function fromJson(string $json): self
    {
        try {
            $data = json_decode($json, true, 16, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw MediaRejected::because(MediaFailureReason::Undecodable, 'unreadable probe output');
        }

        $streams = is_array($data) && is_array($data['streams'] ?? null) ? $data['streams'] : [];
        $video = self::first($streams, 'video');
        $audio = self::first($streams, 'audio');

        if ($video === null) {
            throw MediaRejected::because(MediaFailureReason::Undecodable, 'no video stream');
        }

        $width = (int) ($video['width'] ?? 0);
        $height = (int) ($video['height'] ?? 0);
        $duration = self::number($data['format']['duration'] ?? null) ?? self::number($video['duration'] ?? null);

        if ($duration === null || $duration <= 0) {
            throw MediaRejected::because(MediaFailureReason::Undecodable, 'no duration');
        }

        // A phone held upright stores landscape frames plus a quarter-turn rotation.
        if (abs(self::rotation($video)) % 180 === 90) {
            [$width, $height] = [$height, $width];
        }

        return new self(
            duration: $duration,
            width: $width,
            height: $height,
            frameRate: self::rate($video['avg_frame_rate'] ?? null) ?? self::rate($video['r_frame_rate'] ?? null) ?? 0.0,
            videoIndex: (int) $video['index'],
            videoCodec: (string) ($video['codec_name'] ?? ''),
            audioIndex: $audio === null ? null : (int) $audio['index'],
            audioCodec: $audio === null ? null : (string) ($audio['codec_name'] ?? ''),
            audioChannels: $audio === null || ! isset($audio['channels']) ? null : (int) $audio['channels'],
        );
    }

    /**
     * @param  array<mixed>  $streams
     * @return array<string, mixed>|null
     */
    private static function first(array $streams, string $type): ?array
    {
        foreach ($streams as $stream) {
            if (! is_array($stream) || ($stream['codec_type'] ?? null) !== $type || ! isset($stream['index'])) {
                continue;
            }

            if ($type === 'video' && (int) ($stream['disposition']['attached_pic'] ?? 0) === 1) {
                continue;
            }

            return $stream;
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $stream
     */
    private static function rotation(array $stream): int
    {
        foreach ((array) ($stream['side_data_list'] ?? []) as $side) {
            if (is_array($side) && isset($side['rotation'])) {
                return (int) round((float) $side['rotation']);
            }
        }

        return (int) ($stream['tags']['rotate'] ?? 0);
    }

    /**
     * ffprobe writes rates as a fraction, `30000/1001`; `0/0` means unknown.
     */
    private static function rate(mixed $value): ?float
    {
        if (! is_string($value) || ! preg_match('#^(\d+)/(\d+)$#', $value, $m) || (int) $m[2] === 0 || (int) $m[1] === 0) {
            return null;
        }

        return (int) $m[1] / (int) $m[2];
    }

    private static function number(mixed $value): ?float
    {
        return is_numeric($value) ? (float) $value : null;
    }
}
