<?php

use App\Domain\Notifications\Enums\NotificationType;
use App\Domain\Notifications\Models\Notification;
use App\Models\User;

// specs/04 §3 IDOR (another account's notification 404s), the owner decision of 2026-10-01
// (marking read stays open whatever the account's status), specs/11 "Data exposure via page props".

it('404s on another account\'s notification and leaves it unread', function () {
    $viewer = User::factory()->create();
    $theirs = Notification::factory()->create();

    $this->actingAs($viewer)->post("/notifications/{$theirs->id}/read")->assertNotFound();

    expect($theirs->refresh()->read_at)->toBeNull();
});

it('404s on an unknown or malformed id', function (string $id) {
    $this->actingAs(User::factory()->create())->post("/notifications/{$id}/read")->assertNotFound();
})->with(['0199a8f0-0000-7000-8000-000000000000', 'not-a-uuid', '1']);

it('never lists another account\'s notifications', function () {
    $viewer = User::factory()->create();
    Notification::factory()->ofType(NotificationType::AccountBanned, ['reason' => 'someone else'])->create();

    $props = $this->actingAs($viewer)->get('/notifications')->viewData('page')['props'];

    expect($props['notifications']['entries'])->toBe([])
        ->and($props['unreadCount'])->toBe(0);
});

it('lets every signed-in account read and mark its own, whatever its status', function (string $state) {
    $viewer = User::factory()->{$state}()->create();
    $notification = Notification::factory()->forUser($viewer)->create();

    $this->actingAs($viewer)->get('/notifications')->assertOk();
    $this->actingAs($viewer)->post("/notifications/{$notification->id}/read")->assertRedirect();
    $this->actingAs($viewer)->post('/notifications/read')->assertRedirect();

    expect($notification->refresh()->read_at)->not->toBeNull();
})->with(['unverified', 'restricted', 'suspended', 'pendingDeletion']);

it('signs a banned account out instead', function () {
    $viewer = User::factory()->banned()->create();

    $this->actingAs($viewer)->get('/notifications')->assertRedirect('/login');
    $this->assertGuest();
});

it('rate-limits the writes', function () {
    config(['platform.rate_limits.global_write_per_minute' => 2]);
    $viewer = User::factory()->create();

    $this->actingAs($viewer)->from('/notifications')->post('/notifications/read')->assertSessionMissing('error');
    $this->actingAs($viewer)->from('/notifications')->post('/notifications/read')->assertSessionMissing('error');
    $notification = Notification::factory()->forUser($viewer)->create();
    $this->actingAs($viewer)->from('/notifications')->post("/notifications/{$notification->id}/read")
        ->assertRedirect('/notifications')
        ->assertSessionHas('error', 'Too many changes. Wait a minute and try again.');

    expect($notification->refresh()->read_at)->toBeNull();
});

it('puts only rendered text in the props: no raw data, no ids of the account', function () {
    $viewer = User::factory()->create();
    Notification::factory()->forUser($viewer)->ofType(NotificationType::MediaProcessingFailed, ['collection' => 'avatar', 'media' => '01hsecretmediaulidxxxxxxxx'])->create();

    $props = json_encode($this->actingAs($viewer)->get('/notifications')->viewData('page')['props']['notifications']);

    expect($props)->not->toContain('01hsecretmediaulidxxxxxxxx')
        ->and($props)->not->toContain('notifiable')
        ->and($props)->not->toContain('params')
        ->and($props)->not->toContain('group_key');
});

it('renders markup in a staff-written reason as text, not HTML', function () {
    $viewer = User::factory()->create();
    Notification::factory()->forUser($viewer)->ofType(NotificationType::AccountSuspended, ['reason' => '<img src=x onerror=alert(1)>'])->create();

    $body = $this->actingAs($viewer)->get('/notifications')->viewData('page')['props']['notifications']['entries'][0]['body'];

    // Stored and passed as plain text; Vue interpolation escapes it (no v-html on the page).
    expect($body)->toBe('Reason: <img src=x onerror=alert(1)>');
});

it('gives staff no reach into another account\'s notifications', function (string $role) {
    $staff = User::factory()->{$role}()->create();
    $theirs = Notification::factory()->create();

    $this->actingAs($staff)->post("/notifications/{$theirs->id}/read")->assertNotFound();
    $this->actingAs($staff)->post('/notifications/read');

    expect($theirs->refresh()->read_at)->toBeNull();
})->with(['moderator', 'admin', 'superAdmin']);
