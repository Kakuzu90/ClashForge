<?php

namespace App\Domain\Notifications\Policies;

use App\Domain\Notifications\Models\Notification;
use App\Models\User;

/**
 * Notifications are their recipient's alone; staff have no reach into them.
 */
class NotificationPolicy
{
    /**
     * Mark all read touches only the account's own rows, so any signed-in account may.
     */
    public function markAllRead(User $user): bool
    {
        return true;
    }

    public function update(User $user, Notification $notification): bool
    {
        return $notification->notifiable_type === $user->getMorphClass() && $notification->notifiable_id === $user->id;
    }
}
