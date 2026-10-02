<?php

namespace App\Domain\PlayerAccounts\Policies;

use App\Domain\Auth\Enums\StaffAbility;
use App\Domain\PlayerAccounts\Models\CocAccountDispute;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

/**
 * Ownership disputes (specs/13 §5, specs/04 §2). Opening needs what attaching needs; the parties
 * act on their own dispute; only `resolve-disputes` admins decide, and never in their own.
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

    public function decide(User $user, CocAccountDispute $dispute): bool
    {
        return Gate::forUser($user)->allows(StaffAbility::ResolveDisputes->value) && ! $this->isParty($user, $dispute);
    }

    private function isParty(User $user, CocAccountDispute $dispute): bool
    {
        return $dispute->claimant_id === $user->id || $dispute->current_holder_id === $user->id;
    }
}
