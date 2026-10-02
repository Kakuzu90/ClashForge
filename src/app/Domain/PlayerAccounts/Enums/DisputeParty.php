<?php

namespace App\Domain\PlayerAccounts\Enums;

use App\Support\Enums\Concerns\EnumHelpers;
use App\Support\Enums\Contracts\HasLabelAndColor;

/**
 * Which side of a dispute submitted something.
 */
enum DisputeParty: string implements HasLabelAndColor
{
    use EnumHelpers;

    case Claimant = 'claimant';
    case Holder = 'holder';

    public function label(): string
    {
        return match ($this) {
            self::Claimant => 'Claimant',
            self::Holder => 'Holder',
        };
    }

    public function color(): string
    {
        return 'text-muted';
    }
}
