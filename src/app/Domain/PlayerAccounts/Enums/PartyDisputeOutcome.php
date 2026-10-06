<?php

namespace App\Domain\PlayerAccounts\Enums;

use App\Support\Enums\Concerns\EnumHelpers;
use App\Support\Enums\Contracts\HasLabelAndColor;

/**
 * How a closed dispute ended, as one party reads it on their dispute page (P2-16). The notices of
 * P2-18 use the same words, but this covers every ending for both sides, the ones nobody is told
 * about included (the party who acted still sees what they did).
 */
enum PartyDisputeOutcome: string implements HasLabelAndColor
{
    use EnumHelpers;

    case TransferredToYou = 'transferred_to_you';
    case ReleasedToYou = 'released_to_you';
    case TransferredAway = 'transferred_away';
    case ReleasedByYou = 'released_by_you';
    case Denied = 'denied';
    case DeniedToken = 'denied_token';
    case Kept = 'kept';
    case KeptToken = 'kept_token';
    case Suspended = 'suspended';
    case WithdrawnByYou = 'withdrawn_by_you';
    case Withdrawn = 'withdrawn';
    case WithdrawnInactive = 'withdrawn_inactive';
    case VerifiedByToken = 'verified_by_token';

    public static function for(DisputeStatus $status, ?string $closedBy, DisputeParty $viewer): ?self
    {
        $claimant = $viewer === DisputeParty::Claimant;

        return match (true) {
            $status->isActive() => null,
            $status === DisputeStatus::ResolvedTransfer && $closedBy === 'holder' => $claimant ? self::ReleasedToYou : self::ReleasedByYou,
            $status === DisputeStatus::ResolvedTransfer => $claimant ? self::TransferredToYou : self::TransferredAway,
            $status === DisputeStatus::ResolvedDenied && $closedBy === 'token' => $claimant ? self::DeniedToken : self::KeptToken,
            $status === DisputeStatus::ResolvedDenied => $claimant ? self::Denied : self::Kept,
            $status === DisputeStatus::ResolvedSuspended => self::Suspended,
            $status === DisputeStatus::Withdrawn && $closedBy === 'sweep' => $claimant ? self::WithdrawnInactive : self::Withdrawn,
            $status === DisputeStatus::Withdrawn => $claimant ? self::WithdrawnByYou : self::Withdrawn,
            default => self::VerifiedByToken,
        };
    }

    /** The heading of the closed dispute; the page adds what to do next. */
    public function label(): string
    {
        return match ($this) {
            self::TransferredToYou, self::ReleasedToYou => 'The account is now yours',
            self::TransferredAway => 'The admins moved the account to the other player',
            self::ReleasedByYou => 'You gave the account up',
            self::Denied => 'Your claim was not accepted',
            self::DeniedToken => 'The holder verified the account with a token',
            self::Kept => 'The account stays yours',
            self::KeptToken => 'You verified the account with a token',
            self::Suspended => 'The admins suspended the account',
            self::WithdrawnByYou => 'You withdrew your claim',
            self::Withdrawn => 'The claim was withdrawn',
            self::WithdrawnInactive => 'Your claim was closed',
            self::VerifiedByToken => 'The account was verified with a token',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::TransferredToYou, self::ReleasedToYou, self::Kept, self::KeptToken => 'state-success',
            self::Suspended => 'state-danger',
            default => 'text-muted',
        };
    }
}
