<?php

namespace App\Http\Data\Notifications;

use App\Domain\Notifications\Data\NotificationSliceData;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Props for Notifications/Index (FR-NOTIF-1). The unread count is the shared `unreadCount`.
 */
#[TypeScript]
class NotificationIndexPageData extends Data
{
    /**
     * @param  list<NotificationTabData>  $tabs
     */
    public function __construct(
        public ?string $category,
        public array $tabs,
        public NotificationSliceData $notifications,
    ) {}
}
