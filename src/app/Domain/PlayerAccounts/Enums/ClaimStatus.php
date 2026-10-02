<?php

namespace App\Domain\PlayerAccounts\Enums;

use App\Support\Enums\Concerns\EnumHelpers;
use App\Support\Enums\Contracts\HasLabelAndColor;

/**
 * Outcome of a claim attempt. `Pending` is an attached, not yet verified claim.
 */
enum ClaimStatus: string implements HasLabelAndColor
{
    use EnumHelpers;

    case Pending = 'pending';
    case Succeeded = 'succeeded';
    case Failed = 'failed';
    case Rejected = 'rejected';
    case Superseded = 'superseded';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Waiting for verification',
            self::Succeeded => 'Verified',
            self::Failed => 'Failed',
            self::Rejected => 'Rejected',
            self::Superseded => 'Taken over by a newer verification',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'state-info',
            self::Succeeded => 'state-success',
            self::Failed => 'state-danger',
            self::Rejected => 'state-danger',
            self::Superseded => 'text-muted',
        };
    }
}
