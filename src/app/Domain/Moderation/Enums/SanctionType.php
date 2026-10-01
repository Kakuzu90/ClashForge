<?php

namespace App\Domain\Moderation\Enums;

use App\Support\Enums\Concerns\EnumHelpers;
use App\Support\Enums\Contracts\HasLabelAndColor;

/**
 * specs/12 §6. Admins apply suspensions and bans from the user detail (P1-14); warnings and
 * restrictions arrive with the moderator case flow (P3-06).
 */
enum SanctionType: string implements HasLabelAndColor
{
    use EnumHelpers;

    case Warning = 'warning';
    case Restriction = 'restriction';
    case Suspension = 'suspension';
    case Ban = 'ban';

    public function label(): string
    {
        return match ($this) {
            self::Warning => 'Warning',
            self::Restriction => 'Restriction',
            self::Suspension => 'Suspension',
            self::Ban => 'Ban',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Warning, self::Restriction => 'state-warning',
            self::Suspension, self::Ban => 'state-danger',
        };
    }
}
