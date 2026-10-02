<?php

namespace App\Domain\Audit\Enums;

use App\Support\Enums\Concerns\EnumHelpers;
use App\Support\Enums\Contracts\HasLabelAndColor;

/**
 * What a privileged action did (specs/12 §9). Each task that audits something adds its case here,
 * so the admin filter always lists every action the log can hold.
 */
enum AuditAction: string implements HasLabelAndColor
{
    use EnumHelpers;

    case RoleChanged = 'role.changed';
    case SanctionApplied = 'sanction.applied';
    case SanctionLifted = 'sanction.lifted';
    case SanctionExpired = 'sanction.expired';
    case UserAnonymised = 'user.anonymised';
    case UsernameChanged = 'user.username_changed';
    case CocAccountVerified = 'coc_account.verified';
    case CocDisputeOpened = 'coc_dispute.opened';
    case CocDisputeResponded = 'coc_dispute.responded';
    case CocDisputeInfoRequested = 'coc_dispute.info_requested';
    case CocDisputeEscalated = 'coc_dispute.escalated';
    case CocDisputeClosed = 'coc_dispute.closed';

    public function label(): string
    {
        return match ($this) {
            self::RoleChanged => 'Role changed',
            self::SanctionApplied => 'Sanction applied',
            self::SanctionLifted => 'Sanction lifted',
            self::SanctionExpired => 'Sanction ended',
            self::UserAnonymised => 'Account anonymised',
            self::UsernameChanged => 'Username changed',
            self::CocAccountVerified => 'CoC account verified',
            self::CocDisputeOpened => 'Ownership dispute opened',
            self::CocDisputeResponded => 'Ownership dispute answered',
            self::CocDisputeInfoRequested => 'More asked in a dispute',
            self::CocDisputeEscalated => 'Dispute sent to admins',
            self::CocDisputeClosed => 'Ownership dispute closed',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::RoleChanged => 'state-warning',
            self::SanctionApplied, self::UserAnonymised => 'state-danger',
            self::SanctionLifted, self::SanctionExpired => 'state-success',
            self::UsernameChanged => 'state-info',
            self::CocAccountVerified => 'state-success',
            self::CocDisputeOpened, self::CocDisputeEscalated => 'state-warning',
            self::CocDisputeResponded, self::CocDisputeInfoRequested => 'state-info',
            self::CocDisputeClosed => 'text-muted',
        };
    }
}
