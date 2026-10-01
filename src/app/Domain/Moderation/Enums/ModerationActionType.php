<?php

namespace App\Domain\Moderation\Enums;

use App\Support\Enums\Concerns\EnumHelpers;
use App\Support\Enums\Contracts\HasLabelAndColor;

/**
 * specs/07 `moderation_actions.action`. `lift` ends a suspension (later a restriction); `unban`
 * ends a ban.
 */
enum ModerationActionType: string implements HasLabelAndColor
{
    use EnumHelpers;

    case Hide = 'hide';
    case Unhide = 'unhide';
    case Remove = 'remove';
    case Restore = 'restore';
    case Warn = 'warn';
    case Restrict = 'restrict';
    case Suspend = 'suspend';
    case Ban = 'ban';
    case Lift = 'lift';
    case Unban = 'unban';
    case Dismiss = 'dismiss';
    case Escalate = 'escalate';
    case TransferOwnership = 'transfer_ownership';
    case ApproveSeller = 'approve_seller';
    case RejectListing = 'reject_listing';

    public function label(): string
    {
        return match ($this) {
            self::Hide => 'Hid content',
            self::Unhide => 'Unhid content',
            self::Remove => 'Removed content',
            self::Restore => 'Restored content',
            self::Warn => 'Warned',
            self::Restrict => 'Restricted',
            self::Suspend => 'Suspended',
            self::Ban => 'Banned',
            self::Lift => 'Lifted a sanction',
            self::Unban => 'Unbanned',
            self::Dismiss => 'Dismissed',
            self::Escalate => 'Escalated',
            self::TransferOwnership => 'Transferred ownership',
            self::ApproveSeller => 'Approved seller',
            self::RejectListing => 'Rejected listing',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Suspend, self::Ban, self::Remove => 'state-danger',
            self::Warn, self::Restrict, self::Hide, self::Escalate, self::RejectListing => 'state-warning',
            default => 'text-muted',
        };
    }
}
