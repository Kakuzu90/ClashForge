<?php

namespace App\Domain\Bases\Policies;

use App\Models\User;

/**
 * Publishing a base (specs/04 §1–2): a verified email, an account allowed content writes (not
 * restricted, suspended, banned or leaving) and a verified CoC account held (`verified` or
 * `disputed`). Edit and delete arrive with P3-09.
 */
class BaseLayoutPolicy
{
    public function create(User $user): bool
    {
        return $user->hasVerifiedEmail() && $user->allowsContentWrites() && $user->hasVerifiedCocAccount();
    }
}
