<?php

namespace App\Domain\PlayerAccounts\Policies;

use App\Domain\Auth\Enums\UserStatus;
use App\Domain\PlayerAccounts\Enums\CocAccountStatus;
use App\Domain\PlayerAccounts\Models\CocAccount;
use App\Domain\Users\Services\PrivacyPolicyResolver;
use App\Models\User;

/**
 * Attach and verify, own accounts only (specs/04 §2). Attaching needs a verified email (FR-AUTH-4)
 * and an account allowed to write: restricted accounts may still attach and verify, since that is
 * not one of the content writes they lose (specs/04 §3); suspended, banned and pending-deletion
 * accounts may not.
 */
class CocAccountPolicy
{
    public function __construct(private readonly PrivacyPolicyResolver $privacy) {}

    /**
     * The account page (P2-04). The owner sees their own rows in any status but `released`. Anyone
     * else, staff included, sees a verified or disputed row only where they may see the owner's
     * profile with "show accounts" on; a banned or pending-deletion owner hides it (specs/23 §2).
     * Unverified rows are nobody's business but the owner's (specs/17 §2).
     */
    public function view(?User $viewer, CocAccount $account): bool
    {
        $owner = $account->user;

        if ($owner === null || $account->status === CocAccountStatus::Released) {
            return false;
        }

        if ($viewer !== null && $viewer->id === $owner->id) {
            return true;
        }

        return in_array($account->status, [CocAccountStatus::Verified, CocAccountStatus::Disputed], true)
            && ! in_array($owner->status, [UserStatus::Banned, UserStatus::PendingDeletion], true)
            && $this->privacy->canView($viewer, $owner)
            && $this->privacy->settingsFor($owner->id)->showCocAccounts;
    }

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

    /**
     * Detach (specs/13 §6): the owner's `unverified` or `verified` row. A `disputed` row is given
     * up through its dispute, where release is the voluntary transfer (specs/13 §5 3c), and a
     * `suspended` one stays with staff (owner decision 2026-10-05, P2-14).
     */
    public function detach(User $user, CocAccount $account): bool
    {
        return $user->allowsAccountWrites()
            && $account->user_id === $user->id
            && in_array($account->status, [CocAccountStatus::Unverified, CocAccountStatus::Verified], true);
    }

    /**
     * Manual refresh (FR-COC-9, P2-20): the owner's row while it is theirs to use: `unverified`
     * (synced by hand only, specs/09 §6), `verified` or `disputed`. Not `suspended`, which stays
     * with staff, and not `released`.
     */
    public function refresh(User $user, CocAccount $account): bool
    {
        return $user->allowsAccountWrites()
            && $account->user_id === $user->id
            && in_array($account->status, [CocAccountStatus::Unverified, CocAccountStatus::Verified, CocAccountStatus::Disputed], true);
    }

    /**
     * Custom images (FR-COC-11, P2-23 Q1): the owner's `verified` or `disputed` row, as for the
     * featured account. Uploads are content writes, so a verified email and content-write standing
     * (not restricted) are needed, as `MediaPolicy` asks of the upload itself.
     */
    public function manageImages(User $user, CocAccount $account): bool
    {
        return $user->hasVerifiedEmail()
            && $user->allowsContentWrites()
            && $account->user_id === $user->id
            && in_array($account->status, CocAccountStatus::HOLDING, true);
    }

    /**
     * The featured account (FR-COC-12) is one the user holds: `verified` or `disputed`.
     */
    public function feature(User $user, CocAccount $account): bool
    {
        return $user->allowsAccountWrites()
            && $account->user_id === $user->id
            && in_array($account->status, [CocAccountStatus::Verified, CocAccountStatus::Disputed], true);
    }
}
