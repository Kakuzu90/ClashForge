<?php

namespace App\Domain\Auth\Services;

use App\Domain\Auth\Data\AccountStatusData;
use App\Domain\Auth\Enums\UserStatus;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Account status for enforcement (specs/04 §1), and the only writer of `users.status*`, called by
 * Moderation's SanctionService inside its transaction so a sanction applies on the next request.
 */
class UserStatusService
{
    /**
     * Puts the account under a suspension or ban. A ban also ends every session and cycles the
     * remember token, so it cannot sign in from any browser it already holds (specs/04 §1). The
     * acting admin's own session and cookie are untouched.
     */
    public function applySanction(User $user, UserStatus $status, string $reason, ?CarbonImmutable $until): void
    {
        $user->forceFill(['status' => $status, 'status_reason' => $reason, 'status_expires_at' => $until]);

        if ($status === UserStatus::Banned) {
            $user->forceFill(['remember_token' => Str::random(60)]);
            DB::table((string) config('session.table', 'sessions'))->where('user_id', $user->id)->delete();
        }

        $user->save();
    }

    public function clearSanction(User $user): void
    {
        $user->forceFill(['status' => UserStatus::Active, 'status_reason' => null, 'status_expires_at' => null])->save();
    }

    public function effectiveStatus(User $user): UserStatus
    {
        return $user->effectiveStatus();
    }

    public function notice(User $user): AccountStatusData
    {
        $status = $this->effectiveStatus($user);

        return new AccountStatusData(
            status: $status,
            reason: $status === UserStatus::Active ? null : $user->status_reason,
            endsAt: $status === UserStatus::Active ? null : $user->status_expires_at,
        );
    }
}
