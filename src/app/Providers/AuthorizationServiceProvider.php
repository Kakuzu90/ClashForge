<?php

namespace App\Providers;

use App\Domain\Auth\Enums\StaffAbility;
use App\Models\User;
use App\Policies\UserPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

/**
 * Central registration of the staff Gates and the policies that live outside a module (specs/04
 * §3, specs/19 §1). A staff ability needs the role and a status that allows it: read abilities
 * stay open while an account may read (restricted, pending deletion), actions need an active
 * account, and a suspended account has neither.
 *
 * Super admin gets every staff ability from the role hierarchy, except `impersonate`, which no
 * role has. No Gate::before hook: it would also override ownership policies and the rank rule.
 */
class AuthorizationServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        foreach (StaffAbility::cases() as $ability) {
            Gate::define($ability->value, function (User $user) use ($ability): bool {
                $status = $user->effectiveStatus();

                return $ability->grantedTo($user->role)
                    && ($ability->isReadOnly() ? $status->allowsStaffReads() : $status->allowsStaffActions());
            });
        }

        Gate::policy(User::class, UserPolicy::class);
    }
}
