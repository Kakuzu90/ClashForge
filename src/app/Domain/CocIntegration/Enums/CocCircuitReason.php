<?php

namespace App\Domain\CocIntegration\Enums;

use App\Support\Enums\Concerns\EnumHelpers;
use App\Support\Enums\Contracts\HasLabelAndColor;

/**
 * Why the breaker opened: the game's announced maintenance, or our own failure count.
 */
enum CocCircuitReason: string implements HasLabelAndColor
{
    use EnumHelpers;

    case Failures = 'failures';
    case Maintenance = 'maintenance';

    public function label(): string
    {
        return match ($this) {
            self::Failures => 'Game API errors',
            self::Maintenance => 'Game maintenance',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Failures => 'state-danger',
            self::Maintenance => 'state-warning',
        };
    }
}
