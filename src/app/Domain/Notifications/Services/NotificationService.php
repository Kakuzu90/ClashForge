<?php

namespace App\Domain\Notifications\Services;

use App\Domain\Notifications\Models\Notification;
use App\Domain\Notifications\Queries\NotificationReadModel;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Gate;

/**
 * Marking notifications read (FR-NOTIF-1). Open to every signed-in account whatever its status:
 * it touches only the account's own rows (owner decision, 2026-10-01).
 */
class NotificationService
{
    public function __construct(private readonly NotificationReadModel $notifications) {}

    /**
     * Marks one read and returns where it points, if anywhere. Another account's id is not found.
     *
     * @throws ModelNotFoundException<Notification>
     */
    public function markRead(User $user, string $id): ?string
    {
        $notification = $this->notifications->of($user)->whereKey($id)->firstOrFail();
        Gate::forUser($user)->authorize('update', $notification);

        if ($notification->read_at === null) {
            $notification->forceFill(['read_at' => Date::now()])->save();
            Cache::forget(Notifier::unreadCacheKey($user->id));
        }

        return NotificationReadModel::render($notification)->url;
    }

    public function markAllRead(User $user): int
    {
        Gate::forUser($user)->authorize('markAllRead', Notification::class);

        $count = $this->notifications->of($user)->whereNull('read_at')->update(['read_at' => Date::now(), 'updated_at' => Date::now()]);
        Cache::forget(Notifier::unreadCacheKey($user->id));

        return $count;
    }
}
