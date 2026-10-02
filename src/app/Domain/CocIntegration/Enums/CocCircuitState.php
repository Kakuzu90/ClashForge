<?php

namespace App\Domain\CocIntegration\Enums;

use App\Support\Enums\Concerns\EnumHelpers;
use App\Support\Enums\Contracts\HasLabelAndColor;

/**
 * Circuit breaker state (specs/09 §7). Half-open: the open period is over and the next call is
 * the probe that decides.
 */
enum CocCircuitState: string implements HasLabelAndColor
{
    use EnumHelpers;

    case Closed = 'closed';
    case Open = 'open';
    case HalfOpen = 'half_open';

    public function label(): string
    {
        return match ($this) {
            self::Closed => 'Game API available',
            self::Open => 'Game API unavailable',
            self::HalfOpen => 'Checking the game API',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Closed => 'state-success',
            self::Open => 'state-danger',
            self::HalfOpen => 'state-warning',
        };
    }
}
