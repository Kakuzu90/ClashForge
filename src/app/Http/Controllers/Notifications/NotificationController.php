<?php

namespace App\Http\Controllers\Notifications;

use App\Domain\Notifications\Enums\NotificationCategory;
use App\Domain\Notifications\Queries\NotificationReadModel;
use App\Domain\Notifications\Services\NotificationService;
use App\Http\Controllers\Controller;
use App\Http\Data\Notifications\NotificationIndexPageData;
use App\Http\Data\Notifications\NotificationTabData;
use App\Http\Requests\Notifications\NotificationIndexRequest;
use App\Models\User;
use App\Support\Seo\PageMeta;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Response;

/**
 * The notification centre (FR-NOTIF-1, specs/16 §6, specs/18 §6). Reading and marking read are
 * open to every signed-in account whatever its status: only its own rows are touched.
 */
class NotificationController extends Controller
{
    public function index(NotificationIndexRequest $request, NotificationReadModel $notifications): Response
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        $category = $request->category();

        $page = new NotificationIndexPageData(
            category: $category?->value,
            tabs: [
                new NotificationTabData(null, 'All'),
                ...array_map(fn (NotificationCategory $c) => new NotificationTabData($c->value, $c->label()), NotificationCategory::inUse()),
            ],
            notifications: $notifications->page($user, $category, (int) config('platform.notifications.per_page'), $request->cursor()),
        );

        return PageMeta::page('Notifications/Index', $page->toArray(), new PageMeta(title: 'Notifications', noindex: true));
    }

    /**
     * Marks one read, then opens what it points to; without a target, back to the list.
     */
    public function read(Request $request, string $id, NotificationService $notifications): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        $target = $notifications->markRead($user, $id);

        return $target === null ? back() : redirect()->to($target);
    }

    public function readAll(Request $request, NotificationService $notifications): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        $notifications->markAllRead($user);

        return back()->with('success', 'All notifications marked as read.');
    }
}
