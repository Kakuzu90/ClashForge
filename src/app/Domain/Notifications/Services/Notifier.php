<?php

namespace App\Domain\Notifications\Services;

use App\Domain\Notifications\Data\InAppMessageData;
use App\Domain\Notifications\Models\Notification;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Writes in-app notifications (specs/16 §1). Called from queued work only, never in a request the
 * user waits on. The unread count is dropped from the cache on every write (specs/21 §3).
 */
class Notifier
{
    public static function unreadCacheKey(int $userId): string
    {
        return "notif:unread:{$userId}";
    }

    public function send(User $user, InAppMessageData $message): ?string
    {
        return DB::transaction(function () use ($user, $message): ?string {
            // Serialize with anonymisation, so delayed work cannot recreate cleared rows.
            $owner = User::query()->lockForUpdate()->find($user->id);
            if ($owner === null) {
                return null;
            }

            $notification = Notification::query()->create([
                'type' => $message->type->value,
                'notifiable_type' => $owner->getMorphClass(),
                'notifiable_id' => $owner->id,
                'data' => ['params' => $message->params],
                'group_key' => $message->groupKey,
            ]);

            DB::afterCommit(fn () => Cache::forget(self::unreadCacheKey($owner->id)));

            return $notification->id;
        });
    }
}
