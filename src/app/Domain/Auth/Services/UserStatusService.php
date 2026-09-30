<?php

namespace App\Domain\Auth\Services;

use App\Domain\Auth\Data\AccountStatusData;
use App\Domain\Auth\Enums\UserStatus;
use App\Models\User;

/**
 * Reads account status for enforcement (specs/04 §1). Applying and lifting sanctions joins with
 * the admin tools (P1-06).
 */
class UserStatusService
{
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
