<?php

namespace App\Domain\PlayerAccounts\Enums;

use App\Support\Enums\Concerns\EnumHelpers;
use App\Support\Enums\Contracts\HasLabelAndColor;

/**
 * Where an ownership dispute stands (specs/13 §5, specs/07). `open` is the holder's response window;
 * `awaiting_admin` follows a response or the end of that window; `awaiting_claimant` /
 * `awaiting_holder` mean an admin asked that party for more.
 */
enum DisputeStatus: string implements HasLabelAndColor
{
    use EnumHelpers;

    case Open = 'open';
    case AwaitingAdmin = 'awaiting_admin';
    case AwaitingClaimant = 'awaiting_claimant';
    case AwaitingHolder = 'awaiting_holder';
    case ResolvedTransfer = 'resolved_transfer';
    case ResolvedDenied = 'resolved_denied';
    case ResolvedSuspended = 'resolved_suspended';
    case Withdrawn = 'withdrawn';
    case AutoResolved = 'auto_resolved';

    /** The statuses of a dispute still running. */
    public const ACTIVE = [self::Open, self::AwaitingAdmin, self::AwaitingClaimant, self::AwaitingHolder];

    public function isActive(): bool
    {
        return in_array($this, self::ACTIVE, true);
    }

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Waiting for the holder',
            self::AwaitingAdmin => 'With an admin',
            self::AwaitingClaimant => 'Waiting for the claimant',
            self::AwaitingHolder => 'Waiting for the holder',
            self::ResolvedTransfer => 'Transferred',
            self::ResolvedDenied => 'Denied',
            self::ResolvedSuspended => 'Account suspended',
            self::Withdrawn => 'Withdrawn',
            self::AutoResolved => 'Resolved by a token',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Open, self::AwaitingAdmin, self::AwaitingClaimant, self::AwaitingHolder => 'state-warning',
            self::ResolvedTransfer, self::AutoResolved => 'state-success',
            self::ResolvedDenied, self::Withdrawn => 'text-muted',
            self::ResolvedSuspended => 'state-danger',
        };
    }
}
