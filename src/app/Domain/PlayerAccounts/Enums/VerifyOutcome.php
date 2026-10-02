<?php

namespace App\Domain\PlayerAccounts\Enums;

use App\Support\Enums\Concerns\EnumHelpers;
use App\Support\Enums\Contracts\HasLabelAndColor;

/**
 * Result of a token verification (specs/13 §3 steps 7–8).
 */
enum VerifyOutcome: string implements HasLabelAndColor
{
    use EnumHelpers;

    case Verified = 'verified';
    case InvalidToken = 'invalid_token';
    case NotFound = 'not_found';
    case Unavailable = 'unavailable';
    case RateLimited = 'rate_limited';
    case TagSuspended = 'tag_suspended';

    public function label(): string
    {
        return match ($this) {
            self::Verified => 'Verified',
            self::InvalidToken => 'Token not accepted',
            self::NotFound => 'No player with that tag',
            self::Unavailable => 'The game API is unavailable',
            self::RateLimited => 'Too many attempts',
            self::TagSuspended => 'Suspended while staff review it',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Verified => 'state-success',
            self::InvalidToken => 'state-warning',
            self::NotFound => 'state-warning',
            self::Unavailable => 'state-danger',
            self::RateLimited => 'state-warning',
            self::TagSuspended => 'state-danger',
        };
    }
}
