<?php

namespace App\Domain\PlayerAccounts\Enums;

use App\Support\Enums\Concerns\EnumHelpers;
use App\Support\Enums\Contracts\HasLabelAndColor;

/**
 * Why a tag went back to `released` (specs/13 §6): its owner removed it, their account was deleted,
 * or their ban ran `coc.accounts.ban_release_days`.
 */
enum ReleaseReason: string implements HasLabelAndColor
{
    use EnumHelpers;

    case Detach = 'detach';
    case Deletion = 'deletion';
    case Ban = 'ban';

    public function label(): string
    {
        return match ($this) {
            self::Detach => 'Removed by the owner',
            self::Deletion => 'Owner deleted their account',
            self::Ban => 'Owner banned',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Detach => 'state-info',
            self::Deletion, self::Ban => 'text-muted',
        };
    }
}
