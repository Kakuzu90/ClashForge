<?php

namespace App\Domain\Media\Enums;

use App\Support\Enums\Concerns\EnumHelpers;
use App\Support\Enums\Contracts\HasLabelAndColor;
use Illuminate\Support\Number;

/**
 * Stored in `media.failure_reason`; `message()` is what the uploader sees (specs/10 §10). `label()`
 * is the image wording, kept for places that do not know the kind.
 */
enum MediaFailureReason: string implements HasLabelAndColor
{
    use EnumHelpers;

    case ObjectMissing = 'object_missing';
    case SizeMismatch = 'size_mismatch';
    case Undecodable = 'undecodable';
    case TooLarge = 'dimensions_too_large';
    case TooSmall = 'dimensions_too_small';
    case Animated = 'animated';
    case TooLong = 'duration_too_long';
    case FrameRateTooHigh = 'frame_rate_too_high';
    case UnsupportedCodec = 'unsupported_codec';
    case OutputTooLarge = 'output_too_large';
    case Suspicious = 'suspicious_content';
    case ProcessingError = 'processing_error';

    public function label(): string
    {
        return $this->message(MediaKind::Image);
    }

    public function message(MediaKind $kind): string
    {
        $video = $kind === MediaKind::Video;

        return match ($this) {
            self::ObjectMissing => 'The upload did not reach storage. Try uploading it again.',
            self::SizeMismatch => 'The uploaded file does not match the size you selected. Try uploading it again.',
            self::Undecodable => $video
                ? 'This video could not be read. It may be damaged; try exporting it again.'
                : 'This image could not be read. It may be damaged; try exporting it again.',
            self::TooLarge => $video
                ? 'This video is too large. The limit is '.config('media.video.max_long_side').' × '.config('media.video.max_short_side').' pixels.'
                : 'This image is too large. The limit is '.$this->limit('max').' pixels.',
            self::TooSmall => 'This image is too small. It needs to be at least '.$this->limit('min').' pixels.',
            self::Animated => 'Animated images are not supported here. Upload a still image.',
            self::TooLong => 'This video is longer than '.config('media.video.max_duration').' seconds. Trim it and upload it again.',
            self::FrameRateTooHigh => 'This video has too many frames per second. The limit is '.config('media.video.max_frame_rate').'.',
            self::UnsupportedCodec => 'This video uses a format we cannot convert. Export it as H.264 or HEVC with AAC audio and upload it again.',
            self::OutputTooLarge => 'This video is still over '.Number::fileSize((int) config('media.video.max_output_bytes')).' after compression. Upload a shorter clip.',
            self::Suspicious => 'This file is being held for review and was not published.',
            self::ProcessingError => $video
                ? 'Something went wrong while processing this video. Try uploading it again.'
                : 'Something went wrong while processing this image. Try uploading it again.',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Suspicious => 'state-warning',
            default => 'state-danger',
        };
    }

    private function limit(string $bound): string
    {
        return config("media.image.{$bound}_width").' × '.config("media.image.{$bound}_height");
    }
}
