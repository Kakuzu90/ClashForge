<?php

namespace App\Domain\Clans\Enums;

use App\Support\Enums\Concerns\EnumHelpers;
use App\Support\Enums\Contracts\HasLabelAndColor;

/**
 * A member's role as the API names it (specs/07 `coc_accounts.clan_role`). The API's `admin` is
 * the game's Elder.
 */
enum ClanRole: string implements HasLabelAndColor
{
    use EnumHelpers;

    case Member = 'member';
    case Elder = 'admin';
    case CoLeader = 'coLeader';
    case Leader = 'leader';

    public function label(): string
    {
        return match ($this) {
            self::Member => 'Member',
            self::Elder => 'Elder',
            self::CoLeader => 'Co-leader',
            self::Leader => 'Leader',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Leader, self::CoLeader => 'brand',
            default => 'text-muted',
        };
    }
}
