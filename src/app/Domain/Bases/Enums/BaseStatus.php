<?php

namespace App\Domain\Bases\Enums;

use App\Support\Enums\Concerns\EnumHelpers;
use App\Support\Enums\Contracts\HasLabelAndColor;

/**
 * Where a base is in its life (specs/07 `base_layouts.status`). `Processing` waits for its media
 * (FR-BASE-5); `Hidden` and `Removed` are moderation's (P3-06).
 */
enum BaseStatus: string implements HasLabelAndColor
{
    use EnumHelpers;

    case Draft = 'draft';
    case Processing = 'processing';
    case Published = 'published';
    case Hidden = 'hidden';
    case Removed = 'removed';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Processing => 'Processing',
            self::Published => 'Published',
            self::Hidden => 'Hidden',
            self::Removed => 'Removed',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Published => 'state-success',
            self::Processing, self::Draft => 'text-muted',
            self::Hidden => 'state-warning',
            self::Removed => 'state-danger',
        };
    }
}
