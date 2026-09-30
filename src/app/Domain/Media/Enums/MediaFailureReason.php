<?php

namespace App\Domain\Media\Enums;

use App\Support\Enums\Concerns\EnumHelpers;
use App\Support\Enums\Contracts\HasLabelAndColor;

/**
 * Stored in `media.failure_reason`; the label is what the uploader sees (specs/10 §10).
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
    case Suspicious = 'suspicious_content';
    case ProcessingError = 'processing_error';

    public function label(): string
    {
        return match ($this) {
            self::ObjectMissing => 'The upload did not reach storage. Try uploading it again.',
            self::SizeMismatch => 'The uploaded file does not match the size you selected. Try uploading it again.',
            self::Undecodable => 'This image could not be read. It may be damaged; try exporting it again.',
            self::TooLarge => 'This image is too large. The limit is '.$this->limit('max').' pixels.',
            self::TooSmall => 'This image is too small. It needs to be at least '.$this->limit('min').' pixels.',
            self::Animated => 'Animated images are not supported here. Upload a still image.',
            self::Suspicious => 'This file is being held for review and was not published.',
            self::ProcessingError => 'Something went wrong while processing this image. Try uploading it again.',
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
