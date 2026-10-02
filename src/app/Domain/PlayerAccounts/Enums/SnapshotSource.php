<?php

namespace App\Domain\PlayerAccounts\Enums;

use App\Support\Enums\Concerns\EnumHelpers;
use App\Support\Enums\Contracts\HasLabelAndColor;

/**
 * What wrote a `coc_account_snapshots` row (specs/07).
 */
enum SnapshotSource: string implements HasLabelAndColor
{
    use EnumHelpers;

    case Scheduled = 'scheduled';
    case Manual = 'manual';
    case Verification = 'verification';

    public function label(): string
    {
        return match ($this) {
            self::Scheduled => 'Scheduled sync',
            self::Manual => 'Manual refresh',
            self::Verification => 'Verification',
        };
    }

    public function color(): string
    {
        return 'text-muted';
    }
}
