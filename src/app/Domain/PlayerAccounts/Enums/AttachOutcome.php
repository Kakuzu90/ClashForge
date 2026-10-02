<?php

namespace App\Domain\PlayerAccounts\Enums;

use App\Support\Enums\Concerns\EnumHelpers;
use App\Support\Enums\Contracts\HasLabelAndColor;

/**
 * Result of an attach preview or attach (specs/13 §3 steps 1–5).
 */
enum AttachOutcome: string implements HasLabelAndColor
{
    use EnumHelpers;

    case Ready = 'ready';
    case Attached = 'attached';
    case AlreadyAttached = 'already_attached';
    case NotFound = 'not_found';
    case VerifiedElsewhere = 'verified_elsewhere';
    case Unavailable = 'unavailable';
    case RateLimited = 'rate_limited';

    public function label(): string
    {
        return match ($this) {
            self::Ready => 'Ready to attach',
            self::Attached => 'Attached',
            self::AlreadyAttached => "You've already added this account",
            self::NotFound => 'No player with that tag',
            self::VerifiedElsewhere => 'Verified by another user',
            self::Unavailable => 'The game API is unavailable',
            self::RateLimited => 'Too many attempts',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Ready => 'state-info',
            self::Attached => 'state-success',
            self::AlreadyAttached => 'state-info',
            self::NotFound => 'state-warning',
            self::VerifiedElsewhere => 'state-warning',
            self::Unavailable => 'state-danger',
            self::RateLimited => 'state-warning',
        };
    }
}
