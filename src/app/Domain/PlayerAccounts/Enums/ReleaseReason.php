<?php

namespace App\Domain\PlayerAccounts\Enums;

use App\Support\Enums\Concerns\EnumHelpers;
use App\Support\Enums\Contracts\HasLabelAndColor;

/**
 * Why a tag went back to `released` (specs/13 §6). The deletion window and the 30-day ban join
 * with P2-24.
 */
enum ReleaseReason: string implements HasLabelAndColor
{
    use EnumHelpers;

    case Detach = 'detach';

    public function label(): string
    {
        return match ($this) {
            self::Detach => 'Removed by the owner',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Detach => 'state-info',
        };
    }
}
