<?php

namespace App\Domain\Audit\Enums;

use App\Support\Enums\Concerns\EnumHelpers;
use App\Support\Enums\Contracts\HasLabelAndColor;

/**
 * The kind of record an entry is about, stored in `auditable_type`. A short name rather than a
 * class name, so the log survives refactors and Audit stays a leaf module (specs/19 §2).
 */
enum AuditSubject: string implements HasLabelAndColor
{
    use EnumHelpers;

    case User = 'user';
    case CocAccount = 'coc_account';
    case CocAccountDispute = 'coc_account_dispute';

    public function label(): string
    {
        return match ($this) {
            self::User => 'Account',
            self::CocAccount => 'CoC account',
            self::CocAccountDispute => 'Ownership dispute',
        };
    }

    public function color(): string
    {
        return 'text-muted';
    }
}
