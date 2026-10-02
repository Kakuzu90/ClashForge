<?php

namespace App\Domain\PlayerAccounts\Enums;

use App\Support\Enums\Concerns\EnumHelpers;
use App\Support\Enums\Contracts\HasLabelAndColor;

/**
 * Why a claim attempt failed (specs/07). `NotFound` is ours: the API knows no player with the tag.
 */
enum ClaimFailureReason: string implements HasLabelAndColor
{
    use EnumHelpers;

    case InvalidToken = 'invalid_token';
    case AlreadyClaimed = 'already_claimed';
    case ApiError = 'api_error';
    case RateLimited = 'rate_limited';
    case NotFound = 'not_found';

    public function label(): string
    {
        return match ($this) {
            self::InvalidToken => 'Token not accepted',
            self::AlreadyClaimed => 'Verified by another user',
            self::ApiError => 'Game API unavailable',
            self::RateLimited => 'Too many attempts',
            self::NotFound => 'No player with that tag',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::InvalidToken => 'state-warning',
            self::AlreadyClaimed => 'state-warning',
            self::ApiError => 'state-danger',
            self::RateLimited => 'state-warning',
            self::NotFound => 'state-warning',
        };
    }
}
