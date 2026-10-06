<?php

namespace App\Domain\PlayerAccounts\Policies;

use App\Domain\Auth\Enums\StaffAbility;
use App\Domain\PlayerAccounts\Enums\DisputeParty;
use App\Domain\PlayerAccounts\Enums\DisputeStatus;
use App\Domain\PlayerAccounts\Models\CocAccountDispute;
use App\Domain\PlayerAccounts\Support\DisputeRank;
use App\Domain\PlayerAccounts\Support\DisputeStake;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

/**
 * Ownership disputes (specs/13 §5, specs/04 §2). Opening needs what attaching needs; the parties
 * act on their own dispute; only `resolve-disputes` admins review and decide, never their own. A
 * decision also needs to strictly outrank both parties (specs/04 §2 rule 1, owner decision
 * 2026-10-05, P2-17): an admin party waits for a super admin.
 */
class CocAccountDisputePolicy
{
    public function open(User $user): bool
    {
        return $user->hasVerifiedEmail() && $user->allowsAccountWrites();
    }

    public function view(User $user, CocAccountDispute $dispute): bool
    {
        return $this->isParty($user, $dispute) || Gate::forUser($user)->allows(StaffAbility::ResolveDisputes->value);
    }

    public function respond(User $user, CocAccountDispute $dispute): bool
    {
        return $user->allowsAccountWrites() && $this->isParty($user, $dispute);
    }

    public function withdraw(User $user, CocAccountDispute $dispute): bool
    {
        return $user->allowsAccountWrites() && $dispute->claimant_id === $user->id;
    }

    public function release(User $user, CocAccountDispute $dispute): bool
    {
        return $user->allowsAccountWrites() && $dispute->current_holder_id === $user->id;
    }

    /**
     * The staff review page: the parties' private evidence and history, so never for staff with a
     * stake in the tag, the parties included (owner decisions 2026-10-05 and 2026-10-06, P2-17).
     */
    public function review(User $user, CocAccountDispute $dispute): bool
    {
        return Gate::forUser($user)->allows(StaffAbility::ResolveDisputes->value) && ! DisputeStake::involves($user, $dispute);
    }

    public function decide(User $user, CocAccountDispute $dispute): bool
    {
        return $this->review($user, $dispute) && $this->outranksParties($user, $dispute);
    }

    /**
     * Releasing the tag a dispute suspended (specs/13 §2, P2-25): a reviewer of a dispute that
     * ended suspended, who strictly outranks the row's holder (specs/04 §2 rule 1). Whether the row
     * is still this dispute's suspension is checked under the lock, so a late click gets a reason
     * ("This tag is no longer suspended.") rather than a 403.
     */
    public function releaseTag(User $user, CocAccountDispute $dispute): bool
    {
        return $this->review($user, $dispute)
            && $dispute->status === DisputeStatus::ResolvedSuspended
            && DisputeRank::outranks($user, $dispute->account?->user);
    }

    /**
     * Removing an evidence image that shows an identity document (specs/13 §9, P2-25): a reviewer
     * who strictly outranks the party who sent it.
     */
    public function removeEvidence(User $user, CocAccountDispute $dispute, DisputeParty $party): bool
    {
        return $this->review($user, $dispute)
            && DisputeRank::outranks($user, $party === DisputeParty::Claimant ? $dispute->claimant : $dispute->holder);
    }

    public function outranksParties(User $user, CocAccountDispute $dispute): bool
    {
        return DisputeRank::outranksParties($user, $dispute);
    }

    private function isParty(User $user, CocAccountDispute $dispute): bool
    {
        return $dispute->claimant_id === $user->id || $dispute->current_holder_id === $user->id;
    }
}
