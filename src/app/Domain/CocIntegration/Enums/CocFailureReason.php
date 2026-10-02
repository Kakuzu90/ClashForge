<?php

namespace App\Domain\CocIntegration\Enums;

use App\Support\Enums\Concerns\EnumHelpers;
use App\Support\Enums\Contracts\HasLabelAndColor;

/**
 * Why the API could not answer (specs/09 §7). Background jobs use it to choose between releasing
 * with `retryAfter` and backing off; the UI shows one "unavailable" message for all of them.
 */
enum CocFailureReason: string implements HasLabelAndColor
{
    use EnumHelpers;

    case Throttled = 'throttled';
    case Maintenance = 'maintenance';
    case ServerError = 'server_error';
    case Timeout = 'timeout';
    case NoHealthyKey = 'no_healthy_key';
    case Malformed = 'malformed';
    case CircuitOpen = 'circuit_open';

    public function label(): string
    {
        return match ($this) {
            self::Throttled => 'Rate limited by the game API',
            self::Maintenance => 'Game maintenance',
            self::ServerError => 'Game API error',
            self::Timeout => 'Game API timed out',
            self::NoHealthyKey => 'No working API key',
            self::Malformed => 'Unexpected response from the game API',
            self::CircuitOpen => 'Game API paused after errors',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Throttled, self::Maintenance => 'state-warning',
            self::ServerError, self::Timeout, self::NoHealthyKey, self::Malformed, self::CircuitOpen => 'state-danger',
        };
    }
}
