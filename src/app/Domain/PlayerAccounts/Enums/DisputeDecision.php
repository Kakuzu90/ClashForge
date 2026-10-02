<?php

namespace App\Domain\PlayerAccounts\Enums;

use App\Support\Enums\Concerns\EnumHelpers;
use App\Support\Enums\Contracts\HasLabelAndColor;

/**
 * What an admin can do with a dispute (specs/13 §5 step 4).
 */
enum DisputeDecision: string implements HasLabelAndColor
{
    use EnumHelpers;

    case Transfer = 'transfer';
    case Deny = 'deny';
    case Suspend = 'suspend';
    case AskClaimant = 'ask_claimant';
    case AskHolder = 'ask_holder';

    public function label(): string
    {
        return match ($this) {
            self::Transfer => 'Transfer to the claimant',
            self::Deny => 'Deny the claim',
            self::Suspend => 'Suspend the account',
            self::AskClaimant => 'Ask the claimant for more',
            self::AskHolder => 'Ask the holder for more',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Transfer => 'state-success',
            self::Deny => 'text-muted',
            self::Suspend => 'state-danger',
            self::AskClaimant, self::AskHolder => 'state-info',
        };
    }
}
