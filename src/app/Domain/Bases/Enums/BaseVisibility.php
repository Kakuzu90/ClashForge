<?php

namespace App\Domain\Bases\Enums;

use App\Support\Enums\Concerns\EnumHelpers;
use App\Support\Enums\Contracts\HasLabelAndColor;

/**
 * Who sees a published base (FR-BASE-1): everyone, anyone with the link, or the author only.
 */
enum BaseVisibility: string implements HasLabelAndColor
{
    use EnumHelpers;

    case Public = 'public';
    case Unlisted = 'unlisted';
    case Private = 'private';

    public function label(): string
    {
        return match ($this) {
            self::Public => 'Public',
            self::Unlisted => 'Unlisted',
            self::Private => 'Private',
        };
    }

    public function color(): string
    {
        return 'text-muted';
    }
}
