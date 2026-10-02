<?php

namespace App\Domain\CocIntegration\Enums;

use App\Support\Enums\Concerns\EnumHelpers;
use App\Support\Enums\Contracts\HasLabelAndColor;

/**
 * Outcome of a player or clan lookup. `Unavailable` covers every API-side failure, so a caller
 * degrades instead of erroring (specs/09 §7).
 */
enum CocLookupStatus: string implements HasLabelAndColor
{
    use EnumHelpers;

    case Found = 'found';
    case NotFound = 'not_found';
    case Unavailable = 'unavailable';

    public function label(): string
    {
        return match ($this) {
            self::Found => 'Found',
            self::NotFound => 'No player or clan with that tag',
            self::Unavailable => 'The game API is unavailable',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Found => 'state-success',
            self::NotFound => 'state-warning',
            self::Unavailable => 'state-danger',
        };
    }
}
