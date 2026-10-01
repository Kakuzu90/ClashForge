<?php

use App\Domain\Notifications\Models\Notification;
use App\Models\User;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Date;

// specs/16 §7, specs/20 §3: read rows go after 90 days, unread after 180, at most 500 per account
// with the oldest read ones trimmed first.

beforeEach(fn () => Date::setTestNow('2026-10-01 12:00:00'));

it('deletes read rows past 90 days and unread rows past 180, keeping the rest', function () {
    $user = User::factory()->create();
    $readDays = (int) config('platform.notifications.prune_read_days');
    $unreadDays = (int) config('platform.notifications.prune_unread_days');

    $oldRead = Notification::factory()->forUser($user)->read()->createdAt(now()->subDays($readDays)->subSecond())->create();
    $keptRead = Notification::factory()->forUser($user)->read()->createdAt(now()->subDays($readDays))->create();
    $oldUnread = Notification::factory()->forUser($user)->createdAt(now()->subDays($unreadDays)->subSecond())->create();
    $keptUnread = Notification::factory()->forUser($user)->createdAt(now()->subDays($readDays + 1))->create();

    $this->artisan('notifications:prune')->expectsOutputToContain('Deleted 1 read, 1 unread and 0 over-cap notifications.')->assertSuccessful();

    expect(Notification::query()->pluck('id')->sort()->values()->all())->toBe(collect([$keptRead->id, $keptUnread->id])->sort()->values()->all())
        ->and(Notification::query()->whereKey([$oldRead->id, $oldUnread->id])->exists())->toBeFalse();
});

it('trims an account over the cap by its oldest read rows, never unread ones', function () {
    config(['platform.notifications.max_per_user' => 3]);
    $user = User::factory()->create();
    $other = User::factory()->create();

    $oldestRead = Notification::factory()->forUser($user)->read()->createdAt(now()->subDays(5))->create();
    $unreadOlder = Notification::factory()->forUser($user)->createdAt(now()->subDays(6))->create();
    $newerRead = Notification::factory()->forUser($user)->read()->createdAt(now()->subDays(4))->create();
    Notification::factory()->count(2)->forUser($user)->createdAt(now()->subDay())->create();
    Notification::factory()->count(3)->forUser($other)->create();

    $this->artisan('notifications:prune')->assertSuccessful();

    expect(Notification::query()->where('notifiable_id', $user->id)->count())->toBe(3)
        ->and(Notification::query()->whereKey([$oldestRead->id, $newerRead->id])->exists())->toBeFalse()
        ->and(Notification::query()->whereKey($unreadOlder->id)->exists())->toBeTrue()
        ->and(Notification::query()->where('notifiable_id', $other->id)->count())->toBe(3);
});

it('is safe to run twice', function () {
    Notification::factory()->read()->createdAt(now()->subYear())->create();

    $this->artisan('notifications:prune')->assertSuccessful();
    $this->artisan('notifications:prune')->expectsOutputToContain('Deleted 0 read, 0 unread and 0 over-cap notifications.')->assertSuccessful();
});

it('runs nightly at 02:15', function () {
    $event = collect(app(Schedule::class)->events())->first(fn ($e) => str_contains((string) $e->command, 'notifications:prune'));

    expect($event)->not->toBeNull()
        ->and($event->expression)->toBe('15 2 * * *');
});

it('counts without deleting on a dry run', function () {
    config(['platform.notifications.max_per_user' => 1]);
    $user = User::factory()->create();
    Notification::factory()->forUser($user)->read()->createdAt(now()->subYear())->create();
    Notification::factory()->forUser($user)->createdAt(now()->subYear())->create();
    Notification::factory()->forUser($user)->read()->createdAt(now()->subDay())->create();
    Notification::factory()->forUser($user)->createdAt(now()->subDay())->create();

    $this->artisan('notifications:prune --dry-run')
        ->expectsOutputToContain('Would delete 1 read, 1 unread and 1 over-cap notifications.')
        ->assertSuccessful();

    expect(Notification::query()->count())->toBe(4);

    $this->artisan('notifications:prune')->expectsOutputToContain('Deleted 1 read, 1 unread and 1 over-cap notifications.')->assertSuccessful();
});
