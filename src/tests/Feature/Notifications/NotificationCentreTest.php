<?php

use App\Domain\Notifications\Data\InAppMessageData;
use App\Domain\Notifications\Enums\NotificationType;
use App\Domain\Notifications\Models\Notification;
use App\Domain\Notifications\Services\Notifier;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

// FR-NOTIF-1, specs/16 §6, specs/18 §6: the notification centre, the bell's count, mark read.

beforeEach(function () {
    Date::setTestNow('2026-10-01 12:00:00');
    $this->user = User::factory()->create();
});

it('sends a guest to sign in', function () {
    $this->get('/notifications')->assertRedirect('/login');
    $this->post('/notifications/read')->assertRedirect('/login');
});

it('lists the account\'s notifications newest first, rendered, with the tabs in use', function () {
    Notification::factory()->forUser($this->user)->ofType(NotificationType::PasswordChanged)->createdAt(now()->subHour())->read()->create();
    $latest = Notification::factory()->forUser($this->user)
        ->ofType(NotificationType::NewDeviceSignIn, ['device' => 'Firefox on Linux', 'country' => 'Germany'])
        ->createdAt(now()->subMinute())
        ->create();

    $this->actingAs($this->user)->get('/notifications')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Notifications/Index')
            ->where('meta.title', 'Notifications')
            ->where('category', null)
            ->where('tabs', [
                ['value' => null, 'label' => 'All'],
                ['value' => 'security', 'label' => 'Security'],
                ['value' => 'bases', 'label' => 'Bases'],
            ])
            ->has('notifications.entries', 2)
            ->where('notifications.entries.0', [
                'id' => $latest->id,
                'category' => 'security',
                'title' => 'New sign-in to your account',
                'body' => 'From a device we have not seen before: Firefox on Linux, Germany. If it was not you, sign that device out and change your password.',
                'hasTarget' => true,
                'read' => false,
                'createdAt' => now()->subMinute()->toIso8601String(),
            ])
            ->where('notifications.entries.1.read', true)
            ->where('unreadCount', 1));
});

it('filters by category', function () {
    Notification::factory()->forUser($this->user)->create();
    Notification::factory()->forUser($this->user)->ofType(NotificationType::MediaProcessingFailed, ['collection' => 'avatar'])->create();

    $this->actingAs($this->user)->get('/notifications?category=bases')
        ->assertInertia(fn (Assert $page) => $page
            ->where('category', 'bases')
            ->has('notifications.entries', 1)
            ->where('notifications.entries.0.title', 'An upload could not be processed'));
});

it('rejects an unknown or unused category and a malformed cursor', function (string $query) {
    $this->actingAs($this->user)->get('/notifications?'.$query)->assertRedirect('/notifications')->assertSessionHasErrors();
})->with(['category=nope', 'category=marketplace', 'cursor=not-a-cursor', 'cursor='.rtrim(strtr(base64_encode('{"_pointsToNextItems":true,"id":5}'), '+/', '-_'), '='),
    'cursor='.rtrim(strtr(base64_encode('{"created_at":"zzz","id":"01a0f5ee-c2c6-712d-b664-6046c4873105","_pointsToNextItems":true}'), '+/', '-_'), '=')]);

it('pages with cursors', function () {
    config(['platform.notifications.per_page' => 2]);
    foreach (range(1, 3) as $i) {
        Notification::factory()->forUser($this->user)->createdAt(now()->subMinutes($i))->create();
    }

    $first = $this->actingAs($this->user)->get('/notifications')->viewData('page')['props']['notifications'];
    expect($first['entries'])->toHaveCount(2)->and($first['olderCursor'])->not->toBeNull()->and($first['newerCursor'])->toBeNull();

    $second = $this->actingAs($this->user)->get('/notifications?cursor='.$first['olderCursor'])->assertOk()->viewData('page')['props']['notifications'];
    expect($second['entries'])->toHaveCount(1)
        ->and($second['entries'][0]['createdAt'])->toBe(now()->subMinutes(3)->toIso8601String());
});

it('shows an empty centre', function () {
    $this->actingAs($this->user)->get('/notifications')
        ->assertInertia(fn (Assert $page) => $page->has('notifications.entries', 0)->where('unreadCount', 0));
});

it('renders a row of a removed type as no longer available', function () {
    Notification::factory()->forUser($this->user)->create(['type' => 'type_since_removed']);

    $this->actingAs($this->user)->get('/notifications')
        ->assertInertia(fn (Assert $page) => $page
            ->where('notifications.entries.0.title', 'This notification is no longer available')
            ->where('notifications.entries.0.category', null)
            ->where('notifications.entries.0.hasTarget', false));
});

it('marks one read and opens its target', function () {
    $notification = Notification::factory()->forUser($this->user)->create();

    $this->actingAs($this->user)->post("/notifications/{$notification->id}/read")->assertRedirect('/settings/security');

    expect($notification->refresh()->read_at)->not->toBeNull();
});

it('marks one without a target read and goes back', function () {
    $notification = Notification::factory()->forUser($this->user)->ofType(NotificationType::SanctionEnded, ['sanction' => 'suspension', 'expired' => true])->create();

    $this->actingAs($this->user)->from('/notifications')->post("/notifications/{$notification->id}/read")->assertRedirect('/notifications');

    expect($notification->refresh()->read_at)->not->toBeNull();
});

it('keeps the first read time when opened again', function () {
    $notification = Notification::factory()->forUser($this->user)->read(now()->subDay())->create();

    $this->actingAs($this->user)->post("/notifications/{$notification->id}/read")->assertRedirect();

    expect($notification->refresh()->read_at->toIso8601String())->toBe(now()->subDay()->toIso8601String());
});

it('marks all read, only the account\'s own', function () {
    Notification::factory()->count(3)->forUser($this->user)->create();
    $other = Notification::factory()->create();

    $this->actingAs($this->user)->from('/notifications')->post('/notifications/read')
        ->assertRedirect('/notifications')
        ->assertSessionHas('success', 'All notifications marked as read.');

    expect(Notification::query()->where('notifiable_id', $this->user->id)->whereNull('read_at')->count())->toBe(0)
        ->and($other->refresh()->read_at)->toBeNull();
});

it('shares the unread count, cached, and drops the cache on writes and reads', function () {
    $this->actingAs($this->user)->get('/settings/profile')->assertInertia(fn (Assert $page) => $page->where('unreadCount', 0));
    expect(Cache::get(Notifier::unreadCacheKey($this->user->id)))->toBe(0);

    $id = app(Notifier::class)->send($this->user, new InAppMessageData(NotificationType::PasswordChanged));
    expect(Cache::has(Notifier::unreadCacheKey($this->user->id)))->toBeFalse();
    $this->actingAs($this->user)->get('/settings/profile')->assertInertia(fn (Assert $page) => $page->where('unreadCount', 1));

    $this->actingAs($this->user)->post("/notifications/{$id}/read");
    expect(Cache::has(Notifier::unreadCacheKey($this->user->id)))->toBeFalse();
    $this->actingAs($this->user)->get('/settings/profile')->assertInertia(fn (Assert $page) => $page->where('unreadCount', 0));
});

it('shares no count with guests', function () {
    $this->get('/')->assertInertia(fn (Assert $page) => $page->where('unreadCount', null));
});

it('keeps the centre within the query budget', function () {
    Notification::factory()->count(30)->forUser($this->user)->create();

    DB::enableQueryLog();
    $this->actingAs($this->user)->get('/notifications')->assertOk();

    expect(count(DB::getQueryLog()))->toBeLessThanOrEqual(25);
});

it('reads its limits from config', function () {
    expect(config('platform.notifications'))->toMatchArray([
        'per_page' => 25,
        'unread_cache_ttl' => 60,
        'prune_read_days' => 90,
        'prune_unread_days' => 180,
        'max_per_user' => 500,
    ]);
});

it('drops the cached count when everything is marked read', function () {
    Notification::factory()->count(2)->forUser($this->user)->create();
    $this->actingAs($this->user)->get('/settings/profile')->assertInertia(fn (Assert $page) => $page->where('unreadCount', 2));

    $this->actingAs($this->user)->post('/notifications/read');

    expect(Cache::has(Notifier::unreadCacheKey($this->user->id)))->toBeFalse();
    $this->actingAs($this->user)->get('/settings/profile')->assertInertia(fn (Assert $page) => $page->where('unreadCount', 0));
});
