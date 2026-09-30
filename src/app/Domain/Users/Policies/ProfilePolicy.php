<?php

namespace App\Domain\Users\Policies;

use App\Domain\Users\Models\Profile;
use App\Models\User;

/**
 * "Edit own profile" is own-only for every role (specs/04 §2). A profile edit is an account write,
 * open to restricted accounts (specs/04 §3). A verified email is not needed: FR-AUTH-4 does not
 * list profile edits, and uploading the avatar checks it on its own (MediaPolicy).
 */
class ProfilePolicy
{
    public function update(User $user, Profile $profile): bool
    {
        return $profile->user_id === $user->id && $user->allowsAccountWrites();
    }
}
