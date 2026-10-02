<?php

namespace App\Domain\PlayerAccounts\Enums;

use App\Support\Enums\Concerns\EnumHelpers;
use App\Support\Enums\Contracts\HasLabelAndColor;

/**
 * Why a user may not attach accounts (specs/04 §1–2): the attach page explains it up front.
 */
enum AttachBlock: string implements HasLabelAndColor
{
    use EnumHelpers;

    case EmailUnverified = 'email_unverified';
    case AccountBlocked = 'account_blocked';

    public function label(): string
    {
        return match ($this) {
            self::EmailUnverified => 'Confirm your email first',
            self::AccountBlocked => 'Not available on your account right now',
        };
    }

    public function color(): string
    {
        return 'state-warning';
    }
}
