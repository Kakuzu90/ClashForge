<?php

namespace App\Domain\Media\Enums;

use App\Support\Enums\Concerns\EnumHelpers;
use App\Support\Enums\Contracts\HasLabelAndColor;

/**
 * Public media is served from the CDN; private media only through short-lived signed URLs (FR-MEDIA-10).
 */
enum MediaVisibility: string implements HasLabelAndColor
{
    use EnumHelpers;

    case Public = 'public';
    case Private = 'private';

    public function label(): string
    {
        return match ($this) {
            self::Public => 'Public',
            self::Private => 'Private',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Public => 'state-info',
            self::Private => 'text-muted',
        };
    }

    /**
     * Bucket prefix for derived files (specs/10 §2).
     */
    public function prefix(): string
    {
        return $this->value;
    }
}
