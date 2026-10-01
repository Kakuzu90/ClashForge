<?php

namespace App\Domain\Users\Policies;

use App\Domain\Users\Models\PrivacySettings;
use App\Models\User;

/**
 * "Edit own profile / privacy" is own-only for every role (specs/04 §2) and an account write, open
 * to restricted accounts (specs/04 §3).
 */
class PrivacySettingsPolicy
{
    public function update(User $user, PrivacySettings $settings): bool
    {
        return $settings->user_id === $user->id && $user->allowsAccountWrites();
    }
}
