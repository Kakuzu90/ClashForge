<?php

namespace App\Domain\Moderation\Enums;

use App\Support\Enums\Concerns\EnumHelpers;
use App\Support\Enums\Contracts\HasLabelAndColor;

/**
 * The fixed reason taxonomy (specs/12 §2), shared by reports, cases and sanctions.
 */
enum ReasonCode: string implements HasLabelAndColor
{
    use EnumHelpers;

    case AccountTrading = 'account_trading';
    case Scam = 'scam';
    case FalseOwnership = 'false_ownership';
    case Nsfw = 'nsfw';
    case Hate = 'hate';
    case Harassment = 'harassment';
    case Impersonation = 'impersonation';
    case StolenContent = 'stolen_content';
    case OffPlatformPayment = 'off_platform_payment';
    case Spam = 'spam';
    case WrongCategory = 'wrong_category';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::AccountTrading => 'Account trading',
            self::Scam => 'Scam',
            self::FalseOwnership => 'False ownership claim',
            self::Nsfw => 'Sexual or graphic content',
            self::Hate => 'Hate',
            self::Harassment => 'Harassment',
            self::Impersonation => 'Impersonation',
            self::StolenContent => 'Stolen content',
            self::OffPlatformPayment => 'Off-platform payment',
            self::Spam => 'Spam',
            self::WrongCategory => 'Wrong category',
            self::Other => 'Other',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::AccountTrading => 'state-danger',
            self::Scam, self::FalseOwnership, self::Nsfw, self::Hate, self::Harassment => 'state-warning',
            default => 'text-muted',
        };
    }
}
