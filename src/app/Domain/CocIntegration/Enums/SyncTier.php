<?php

namespace App\Domain\CocIntegration\Enums;

use App\Support\Enums\Concerns\EnumHelpers;
use App\Support\Enums\Contracts\HasLabelAndColor;

/**
 * How often a resource is synced (specs/09 §6). The interval per tier is `coc.sync.tiers`.
 * `Frozen` is the failing state: weekly retries, then it stops.
 */
enum SyncTier: string implements HasLabelAndColor
{
    use EnumHelpers;

    case Hot = 'hot';
    case Warm = 'warm';
    case Cold = 'cold';
    case Frozen = 'frozen';

    public function label(): string
    {
        return match ($this) {
            self::Hot => 'Hot',
            self::Warm => 'Warm',
            self::Cold => 'Cold',
            self::Frozen => 'Frozen',
        };
    }

    public function color(): string
    {
        return $this === self::Frozen ? 'state-warning' : 'text-muted';
    }

    public function intervalSeconds(): int
    {
        return (int) config("coc.sync.tiers.{$this->value}");
    }
}
