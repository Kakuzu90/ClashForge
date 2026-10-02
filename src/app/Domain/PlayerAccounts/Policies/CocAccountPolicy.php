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

    public function verify(User $user, CocAccount $account): bool
    {
        return $this->attach($user)
            && $account->user_id === $user->id
            && $account->status === CocAccountStatus::Unverified;
    }
}
