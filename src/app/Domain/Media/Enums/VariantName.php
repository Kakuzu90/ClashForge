<?php

namespace App\Domain\Media\Enums;

use App\Support\Enums\Concerns\EnumHelpers;
use App\Support\Enums\Contracts\HasLabelAndColor;

/**
 * Derived renditions (specs/07 `media_variants`). Avatars reuse full/card/thumb for 512/128/48.
 */
enum VariantName: string implements HasLabelAndColor
{
    use EnumHelpers;

    case Thumb = 'thumb';
    case Card = 'card';
    case Full = 'full';
    case Poster = 'poster';
    case Video720p = 'video_720p';

    public function label(): string
    {
        return match ($this) {
            self::Thumb => 'Thumbnail',
            self::Card => 'Card',
            self::Full => 'Full size',
            self::Poster => 'Poster frame',
            self::Video720p => 'Video 720p',
        };
    }

    public function color(): string
    {
        return 'text-muted';
    }
}
