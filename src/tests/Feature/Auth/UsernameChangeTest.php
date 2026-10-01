<?php

use App\Domain\Audit\Enums\AuditAction;
use App\Domain\Audit\Models\AuditLog;
use App\Domain\Auth\Models\UsernameHistory;
use App\Domain\Users\Services\CacheInvalidator;
use App\Models\User;
use Illuminate\Support\Facades\Date;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\Auth\CapturesSecurityLog;

// FR-PROFILE-7 at /settings/profile: one change per 30 days with the current password inline; the
// old name is held for this account for 90 days (specs/23 §1).

uses(CapturesSecurityLog::class);

beforeEach(function () {
    $this->captureSecurityLog();
    Date::setTestNow('2026-10-01 12:00:00');
    $this->user = User::factory()->create(['username' => 'chief', 'password' => 'password']);
});

/**
 * A fresh session each time, so jumping ahead a month does not hit the 30-day session cap.
 */
function renameTo(User $user, string $username, string $password = 'password'): mixed
{
    return test()->flushSession()->actingAs($user)->from('/settings/profile')->put('/settings/profile/username', ['username' => $username, 'current_password' => $password]);
}

it('shows the username card on the profile settings page', function () {
    $this->actingAs($this->user)->get('/settings/profile')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Settings/Profile')
            ->where('username', [
                'username' => 'chief',
                'canChange' => true,
                'needsVerifiedEmail' => false,
                'nextChangeAt' => null,
                'changeDays' => config('platform.auth.username_change_days'),
                'reservationDays' => config('platform.auth.username_reservation_days'),
                'minLength' => 3,
                'maxLength' => 20,
            ]));
});

it('changes the username, holds the old one and records the change', function () {
    renameTo($this->user, '  New_Chief ')
        ->assertRedirect('/settings/profile')
        ->assertSessionHasNoErrors()
        ->assertSessionHas('success', 'Username changed to @new_chief. Links to your old name lead here for '.config('platform.auth.username_reservation_days').' days.');

    $user = $this->user->refresh();
    expect($user->username)->toBe('new_chief')
        ->and($user->username_changed_at?->equalTo(Date::now()))->toBeTrue();

    $history = UsernameHistory::query()->sole();
    expect($history->user_id)->toBe($user->id)
        ->and($history->username)->toBe('chief')
        ->and($history->reserved_forever)->toBeFalse()
        ->and($history->released_at->equalTo(Date::now()))->toBeTrue();

    $audit = AuditLog::query()->sole();
    expect($audit->action)->toBe(AuditAction::UsernameChanged)
        ->and($audit->actor_id)->toBe($user->id)
        ->and($audit->auditable_id)->toBe($user->id)
        ->and($audit->before)->toBe(['username' => 'chief'])
        ->and($audit->after)->toBe(['username' => 'new_chief']);

    expect(collect($this->securityEvents())->firstWhere('message', 'auth.username_changed')['context'])
        ->toMatchArray(['user' => $user->ulid, 'from' => 'chief', 'to' => 'new_chief']);
});

it('counts the typed password as a fresh confirmation', function () {
    renameTo($this->user, 'new_chief')->assertSessionHas('auth.password_confirmed_at', Date::now()->getTimestamp());
});

it('refuses a wrong password and changes nothing', function () {
    renameTo($this->user, 'new_chief', 'wrong')->assertSessionHasErrors(['current_password' => 'That is not your current password.']);

    expect($this->user->refresh()->username)->toBe('chief')
        ->and(UsernameHistory::query()->count())->toBe(0)
        ->and(collect($this->securityEvents())->pluck('message'))->toContain('auth.password_confirm_failed');
});

it('refuses names the rules reject', function (string $name, string $message) {
    User::factory()->create(['username' => 'taken_one']);
    UsernameHistory::factory()->create(['username' => 'held_one', 'released_at' => Date::now()->subDays(10)]);
    UsernameHistory::factory()->permanent()->create(['username' => 'gone_one', 'released_at' => Date::now()->subYears(2)]);

    renameTo($this->user, $name)->assertSessionHasErrors(['username' => $message]);
    expect($this->user->refresh()->username)->toBe('chief');
})->with([
    'same' => ['Chief', 'That is already your username.'],
    'taken' => ['taken_one', 'That username is taken. Pick another.'],
    'held by another account' => ['held_one', 'That username is taken. Pick another.'],
    'held forever' => ['gone_one', 'That username is taken. Pick another.'],
    'reserved' => ['admin', 'That username is reserved. Pick another.'],
    'shape' => ['chief-2', 'Use lowercase letters, numbers and underscores only.'],
]);

it('frees a name held by another account once the hold is over', function () {
    UsernameHistory::factory()->create(['username' => 'old_one', 'released_at' => Date::now()->subDays((int) config('platform.auth.username_reservation_days'))->subMinute()]);

    renameTo($this->user, 'old_one')->assertSessionHasNoErrors();
    expect($this->user->refresh()->username)->toBe('old_one');
});

it('lets the owner take back their own held name', function () {
    renameTo($this->user, 'new_chief')->assertSessionHasNoErrors();
    Date::setTestNow(Date::now()->addDays((int) config('platform.auth.username_change_days')));

    renameTo($this->user->refresh(), 'chief')->assertRedirect('/settings/profile')->assertSessionHasNoErrors();
    expect($this->user->refresh()->username)->toBe('chief')
        ->and(UsernameHistory::query()->pluck('username')->all())->toBe(['chief', 'new_chief']);
});

it('allows one change per waiting period and says when the next opens', function () {
    renameTo($this->user, 'new_chief')->assertSessionHasNoErrors();
    $days = (int) config('platform.auth.username_change_days');

    Date::setTestNow(Date::now()->addDays($days)->subMinute());
    renameTo($this->user->refresh(), 'third_name')->assertSessionHasErrors(['username' => 'You can change your username again on 31 October 2026.']);
    $this->flushSession()->actingAs($this->user)->get('/settings/profile')->assertInertia(fn (Assert $page) => $page
        ->where('username.canChange', false)
        ->where('username.nextChangeAt', Date::parse('2026-10-01 12:00:00')->addDays($days)->toIso8601String()));

    Date::setTestNow(Date::now()->addMinute());
    renameTo($this->user->refresh(), 'third_name')->assertSessionHasNoErrors();
    expect($this->user->refresh()->username)->toBe('third_name');
});

it('forgets the cached profile under both names', function () {
    $old = CacheInvalidator::profileVersion('chief');
    $new = CacheInvalidator::profileVersion('new_chief');

    renameTo($this->user, 'new_chief');

    expect(CacheInvalidator::profileVersion('chief'))->toBeGreaterThan($old)
        ->and(CacheInvalidator::profileVersion('new_chief'))->toBeGreaterThan($new);
});
