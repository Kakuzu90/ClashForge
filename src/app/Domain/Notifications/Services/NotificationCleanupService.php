<?php

namespace App\Domain\Notifications\Services;

use App\Domain\Notifications\Models\EmailDelivery;
use App\Domain\Notifications\Models\Notification;
use App\Domain\Notifications\Models\NotificationPreference;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class NotificationCleanupService
{
    // Called inside the account anonymisation transaction, after locking the account.
    public function deleteFor(User $user): void
    {
        Notification::query()->where('notifiable_type', $user->getMorphClass())
            ->where('notifiable_id', $user->id)->delete();

        NotificationPreference::query()->whereKey($user->id)->delete();
        EmailDelivery::query()->where('user_id', $user->id)->delete();

        DB::afterCommit(fn () => Cache::forget(Notifier::unreadCacheKey($user->id)));
    }
}
