<?php

namespace App\Policies;

use App\Domain\Auth\Enums\StaffAbility;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

/**
 * An account's own security settings, and staff actions on another account (specs/04 §2). Beyond holding the ability, the actor must
 * strictly outrank the target: a moderator never acts on a moderator, an admin never on another
 * admin, and super admins are changed only from the console (rule 1). Nobody acts on themselves.
 */
class UserPolicy
{
    /**
     * Own sessions only, with account-write standing: open to restricted accounts (specs/04 §3).
     */
    public function manageSessions(User $actor, User $target): bool
    {
        return $actor->id === $target->id && $actor->allowsAccountWrites();
    }

    public function changePassword(User $actor, User $target): bool
    {
        return $actor->id === $target->id && $actor->allowsAccountWrites();
    }

    public function warn(User $actor, User $target): bool
    {
        return $this->staffOver($actor, StaffAbility::WarnUser, $target);
    }

    public function restrict(User $actor, User $target): bool
    {
        return $this->staffOver($actor, StaffAbility::RestrictUser, $target);
    }

    public function suspend(User $actor, User $target): bool
    {
        return $this->staffOver($actor, StaffAbility::SuspendUser, $target);
    }

    public function ban(User $actor, User $target): bool
    {
        return $this->staffOver($actor, StaffAbility::BanUser, $target);
    }

    public function liftSanction(User $actor, User $target): bool
    {
        return $this->staffOver($actor, StaffAbility::LiftSanction, $target);
    }

    public function changeRole(User $actor, User $target): bool
    {
        return $this->staffOver($actor, StaffAbility::ManageRoles, $target);
    }

    public function hardDelete(User $actor, User $target): bool
    {
        return $this->staffOver($actor, StaffAbility::HardDeleteUser, $target);
    }

    private function staffOver(User $actor, StaffAbility $ability, User $target): bool
    {
        return Gate::forUser($actor)->allows($ability->value) && $actor->role->outranks($target->role);
    }
}
