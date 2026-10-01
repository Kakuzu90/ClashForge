<?php

namespace App\Domain\Auth\Enums;

use App\Support\Enums\Concerns\EnumHelpers;
use App\Support\Enums\Contracts\HasLabelAndColor;

/**
 * Where a verification link stands. Opening it only shows `Pending`; the button on that page
 * confirms. `label()` is the message the page shows.
 */
enum EmailVerificationOutcome: string implements HasLabelAndColor
{
    use EnumHelpers;

    case Pending = 'pending';
    case Verified = 'verified';
    case AlreadyVerified = 'already_verified';
    case Invalid = 'invalid';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Confirm that this account is yours.',
            self::Verified => 'Your email is confirmed.',
            self::AlreadyVerified => 'Your email is already confirmed.',
            self::Invalid => 'This link has already been used or has expired. Ask for a new one.',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Verified, self::AlreadyVerified => 'state-success',
            self::Pending => 'state-info',
            self::Invalid => 'state-danger',
        };
    }
}
