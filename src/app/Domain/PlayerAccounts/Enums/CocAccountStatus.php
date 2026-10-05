<?php

namespace App\Domain\PlayerAccounts\Enums;

use App\Support\Enums\Concerns\EnumHelpers;
use App\Support\Enums\Contracts\HasLabelAndColor;

/**
 * Ownership state of a tag on the platform (FR-COC-7, specs/13 §2).
 */
enum CocAccountStatus: string implements HasLabelAndColor
{
    use EnumHelpers;

    case Unverified = 'unverified';
    case Verified = 'verified';
    case Disputed = 'disputed';
    case Suspended = 'suspended';
    case Released = 'released';

    /** A row in one of these statuses holds the tag (specs/13 §2) and counts as verified. */
    public const HOLDING = [self::Verified, self::Disputed];

    public function label(): string
    {
        return match ($this) {
            self::Unverified => 'Unverified',
            self::Verified => 'Verified',
            self::Disputed => 'Under review',
            self::Suspended => 'Suspended',
            self::Released => 'Released',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Unverified => 'text-muted',
            self::Verified => 'state-success',
            self::Disputed => 'state-warning',
            self::Suspended => 'state-danger',
            self::Released => 'text-muted',
        };
    }
}
