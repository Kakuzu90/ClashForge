<?php

namespace App\Domain\PlayerAccounts\Policies;

use App\Domain\PlayerAccounts\Enums\CocAccountStatus;
use App\Domain\PlayerAccounts\Models\CocAccount;
use App\Models\User;

/**
 * Attach and verify, own accounts only (specs/04 §2). Attaching needs a verified email (FR-AUTH-4)
 * and an account allowed to write: restricted accounts may still attach and verify, since that is
 * not one of the content writes they lose (specs/04 §3); suspended, banned and pending-deletion
 * accounts may not.
 */
class CocAccountPolicy
{
    public function attach(User $user): bool
    {
        return $user->hasVerifiedEmail() && $user->allowsAccountWrites();
    }

    /**
     * The user's own unverified row, or their own `disputed` row: a holder's token ends a dispute
     * (specs/13 §5 3a, owner decision 2026-10-02, P2-03).
     */
    public function verify(User $user, CocAccount $account): bool
    {
        return $this->attach($user)
            && $account->user_id === $user->id
            && in_array($account->status, [CocAccountStatus::Unverified, CocAccountStatus::Disputed], true);
    }
}
