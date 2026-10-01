<?php

namespace App\Domain\Notifications\Queries;

use App\Domain\Notifications\Data\NotificationItemData;
use App\Domain\Notifications\Data\NotificationSliceData;
use App\Domain\Notifications\Data\RenderedNotificationData;
use App\Domain\Notifications\Enums\NotificationCategory;
use App\Domain\Notifications\Models\Notification;
use App\Domain\Notifications\Services\Notifier;
use App\Models\User;
use App\Support\Pagination\CursorShape;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\Cursor;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * The notification centre and the bell (FR-NOTIF-1, specs/16 §6). Always scoped to one account,
 * so another account's notification is simply not found.
 */
class NotificationReadModel
{
    /**
     * The `created_at` the paginator writes ("2026-10-01 05:27:40"); anything else would reach the
     * timestamp comparison and fail there.
     */
    private const CURSOR_TIME = '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}(\.\d{1,6})?$/';

    public static function acceptsCursor(string $cursor): bool
    {
        $parameters = CursorShape::parameters($cursor);

        return $parameters !== null
            && is_string($parameters['created_at'] ?? null)
            && preg_match(self::CURSOR_TIME, $parameters['created_at']) === 1
            && is_string($parameters['id'] ?? null)
            && Str::isUuid($parameters['id']);
    }

    public function page(User $user, ?NotificationCategory $category, int $perPage, ?string $cursor = null): NotificationSliceData
    {
        $page = $this->of($user)
            ->when($category !== null, fn (Builder $query) => $query->whereIn('type', array_map(fn ($type) => $type->value, $category?->types() ?? [])))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->cursorPaginate($perPage, cursor: Cursor::fromEncoded($cursor));

        $entries = [];
        foreach ($page->items() as $notification) {
            /** @var Notification $notification */
            $rendered = self::render($notification);
            $entries[] = new NotificationItemData(
                id: $notification->id,
                category: $notification->notificationType()?->category()->value,
                title: $rendered->title,
                body: $rendered->body,
                hasTarget: $rendered->url !== null,
                read: $notification->read_at !== null,
                createdAt: $notification->created_at->toIso8601String(),
            );
        }

        return new NotificationSliceData($entries, $page->previousCursor()?->encode(), $page->nextCursor()?->encode());
    }

    public function unreadCount(User $user): int
    {
        return (int) Cache::remember(
            Notifier::unreadCacheKey($user->id),
            (int) config('platform.notifications.unread_cache_ttl'),
            fn (): int => $this->of($user)->whereNull('read_at')->count(),
        );
    }

    /**
     * @return Builder<Notification>
     */
    public function of(User $user): Builder
    {
        return Notification::query()
            ->where('notifiable_type', $user->getMorphClass())
            ->where('notifiable_id', $user->id);
    }

    public static function render(Notification $notification): RenderedNotificationData
    {
        return $notification->notificationType()?->render($notification->params())
            ?? new RenderedNotificationData(title: 'This notification is no longer available', body: '', url: null);
    }
}
