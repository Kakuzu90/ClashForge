<?php

namespace App\Domain\Auth\Enums;

use App\Support\Enums\Concerns\EnumHelpers;
use App\Support\Enums\Contracts\HasLabelAndColor;

/**
 * Where an email-change link stands (FR-AUTH-8). Opening it only shows `Pending`; the button on
 * that page confirms. `label()` is the message the page shows.
 */
enum EmailChangeOutcome: string implements HasLabelAndColor
{
    use EnumHelpers;

    case Pending = 'pending';
    case Changed = 'changed';
    case AlreadyChanged = 'already_changed';
    case Taken = 'taken';
    case WrongAccount = 'wrong_account';
    case Invalid = 'invalid';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Confirm the new email for your account.',
            self::Changed => 'Your email is changed. Every other device was signed out.',
            self::AlreadyChanged => 'This email is already on your account.',
            self::Taken => 'That address belongs to another account, so your email has not changed.',
            self::WrongAccount => 'This link is for a different account. Sign in to that account and open the link again.',
            self::Invalid => 'This link has already been used or has expired. Ask for a new one in your security settings.',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Changed, self::AlreadyChanged => 'state-success',
            self::Pending => 'state-info',
            self::Taken, self::WrongAccount, self::Invalid => 'state-danger',
        };
    }
}
