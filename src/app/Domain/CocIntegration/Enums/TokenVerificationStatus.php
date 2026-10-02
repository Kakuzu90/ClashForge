<?php

namespace App\Domain\CocIntegration\Enums;

use App\Support\Enums\Concerns\EnumHelpers;
use App\Support\Enums\Contracts\HasLabelAndColor;

/**
 * Outcome of `POST /players/{tag}/verifytoken`. The API itself answers only ok or invalid; the
 * other two are ours, so a missing tag or an outage never reads as a wrong token (specs/09 §9).
 */
enum TokenVerificationStatus: string implements HasLabelAndColor
{
    use EnumHelpers;

    case Ok = 'ok';
    case Invalid = 'invalid';
    case NotFound = 'not_found';
    case Unavailable = 'unavailable';

    public function label(): string
    {
        return match ($this) {
            self::Ok => 'Verified',
            self::Invalid => 'Token not accepted',
            self::NotFound => 'No player with that tag',
            self::Unavailable => 'The game API is unavailable',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Ok => 'state-success',
            self::Invalid, self::NotFound => 'state-warning',
            self::Unavailable => 'state-danger',
        };
    }
}
