<?php

namespace App\Domain\PlayerAccounts\Enums;

use App\Support\Enums\Concerns\EnumHelpers;
use App\Support\Enums\Contracts\HasLabelAndColor;

/**
 * What the owner's manual refresh did (FR-COC-9, P2-20). The label is the message the owner sees;
 * the two refusals get their wait added by `RefreshResultData::message()`.
 */
enum RefreshOutcome: string implements HasLabelAndColor
{
    use EnumHelpers;

    // Fresh data stored, whether or not tracked progress changed.
    case Updated = 'updated';
    // The API was slower than `coc.sync.manual_timeout`: a job takes over.
    case Background = 'background';
    case NotFound = 'not_found';
    // The API is down, paused or out of budget: nothing was stored and the cooldown is not spent.
    case Unavailable = 'unavailable';
    // This account was refreshed within `coc.sync.manual_cooldown`.
    case CoolingDown = 'cooling_down';
    // The user reached `coc.sync.manual_per_hour` across all their accounts.
    case TooManyRefreshes = 'too_many_refreshes';

    public function label(): string
    {
        return match ($this) {
            self::Updated => 'Game data updated.',
            self::Background => 'Clash of Clans is slow to answer, so the update will finish in the background. Reload the page in a minute.',
            self::NotFound => "Clash of Clans can't find this tag right now. It may have been renamed or deleted in game.",
            self::Unavailable => 'The game API is unavailable right now, so nothing was updated. Try again in a few minutes.',
            self::CoolingDown => 'This account was refreshed recently.',
            self::TooManyRefreshes => 'You have refreshed a lot of accounts in the last hour.',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Updated, self::Background => 'state-success',
            self::NotFound, self::Unavailable => 'state-danger',
            self::CoolingDown, self::TooManyRefreshes => 'state-warning',
        };
    }

    public function succeeded(): bool
    {
        return $this === self::Updated || $this === self::Background;
    }
}
