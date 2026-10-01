<?php

namespace App\Domain\Auth\Actions;

use App\Models\User;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Support\Facades\DB;
use Laravel\Fortify\Actions\CompletePasswordReset as FortifyCompletePasswordReset;

class CompletePasswordReset extends FortifyCompletePasswordReset
{
    /** @param User $user */
    public function __invoke(StatefulGuard $guard, $user): void
    {
        DB::transaction(function () use ($guard, $user): void {
            $current = User::query()->whereKey($user->id)->lockForUpdate()->first();
            if ($current !== null) {
                parent::__invoke($guard, $current);
            }
        });
    }
}
