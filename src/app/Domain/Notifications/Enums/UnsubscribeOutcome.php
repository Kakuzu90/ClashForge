<?php

namespace App\Domain\Notifications\Enums;

use App\Support\Enums\Concerns\EnumHelpers;
use App\Support\Enums\Contracts\HasLabelAndColor;

enum UnsubscribeOutcome: string implements HasLabelAndColor
{
    use EnumHelpers;

    case Pending = 'pending';
    case Unsubscribed = 'unsubscribed';
    case Invalid = 'invalid';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Stop non-security emails from Clash Commons. Security emails will still reach you.',
            self::Unsubscribed => 'Non-security emails are off. Security emails will still reach you.',
            self::Invalid => 'This link is invalid or has expired. You can change your email preferences in Settings.',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'state-info',
            self::Unsubscribed => 'state-success',
            self::Invalid => 'state-danger',
        };
    }
}
