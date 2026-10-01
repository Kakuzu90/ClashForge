<?php

use App\Domain\Auth\Data\RegistrationData;
use App\Domain\Auth\Data\UsernameFieldRules;
use App\Domain\Auth\Enums\Role;
use App\Domain\Auth\Enums\UserStatus;
use App\Domain\Auth\Models\UsernameHistory;
use App\Domain\Auth\Services\AccountDeletionService;
use App\Domain\Auth\Services\RegistrationGuard;
use App\Domain\Auth\Services\RegistrationService;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    Notification::fake();
    Date::setTestNow('2026-10-01 12:00:00');
});

it('requires a signed-in account on both routes', function () {
    $this->get('/settings/danger-zone')->assertRedirect('/login');
    $this->delete('/settings/danger-zone', ['confirmation' => true, 'current_password' => 'password'])->assertRedirect('/login');
});

it('requires the current password inline even with a recently confirmed session', function () {
    $user = User::factory()->create();
    $this->actingAs($user)
        ->withSession(['auth.password_confirmed_at' => now()->getTimestamp()])
        ->from('/settings/danger-zone')->delete('/settings/danger-zone', ['confirmation' => true])
        ->assertRedirect('/settings/danger-zone')->assertSessionHasErrors('current_password');
    $this->delete('/settings/danger-zone', ['confirmation' => true, 'current_password' => 'wrong'])
        ->assertRedirect('/settings/danger-zone')->assertSessionHasErrors('current_password');
    expect(session()->getOldInput('current_password'))->toBeNull();
    expect(fn () => app(AccountDeletionService::class)->request($user, 'wrong'))->toThrow(ValidationException::class);
    expect($user->refresh()->status)->toBe(UserStatus::Active);
});

it('checks the locked current password rather than a stale authenticated model', function () {
    $user = User::factory()->create();
    User::query()->whereKey($user->id)->update(['password' => Hash::make('changed-password')]);
    expect(fn () => app(AccountDeletionService::class)->request($user, 'password'))->toThrow(ValidationException::class);
    expect($user->refresh()->status)->toBe(UserStatus::Active);
});

it('limits inline current-password guesses with the shared password-confirm bucket', function () {
    $user = User::factory()->create();
    $this->actingAs($user)->from('/settings/danger-zone');
    for ($attempt = 0; $attempt < (int) config('platform.auth.password_confirm_per_minute'); $attempt++) {
        $this->delete('/settings/danger-zone', ['confirmation' => true, 'current_password' => 'wrong'])->assertSessionHasErrors('current_password');
    }
    $this->delete('/settings/danger-zone', ['confirmation' => true, 'current_password' => 'password'])
        ->assertSessionHasErrors('current_password');
    expect($user->refresh()->status)->toBe(UserStatus::Active);
});

it('applies the own-account policy for every role', function (Role $role) {
    $actor = User::factory()->create(['role' => $role]);
    $other = User::factory()->create();
    expect(Gate::forUser($actor)->allows('requestDeletion', $actor))->toBeTrue()
        ->and(Gate::forUser($actor)->allows('requestDeletion', $other))->toBeFalse()
        ->and(Gate::forUser($actor)->allows('viewDangerZone', $other))->toBeFalse();
})->with(Role::cases());

it('rechecks current standing under the service lock', function () {
    $actor = User::factory()->create();
    $this->actingAs($actor)->get('/settings/danger-zone');
    User::query()->whereKey($actor->id)->update(['status' => UserStatus::Suspended]);
    expect(fn () => app(AccountDeletionService::class)->request($actor, 'password'))->toThrow(AuthorizationException::class);
});

it('allows unverified and restricted accounts but refuses suspended banned and pending accounts', function (string $state, bool $allowed) {
    $user = User::factory()->{$state}()->create();
    $response = $this->actingAs($user)
        ->delete('/settings/danger-zone', ['confirmation' => true, 'current_password' => 'password']);
    if ($allowed) {
        $response->assertRedirect('/login');
        expect($user->refresh()->status)->toBe(UserStatus::PendingDeletion);
    } else {
        expect($response->getStatusCode())->toBeIn([302, 403]);
        expect($user->refresh()->deletion_requested_at)->toEqual($state === 'pendingDeletion' ? now() : null);
    }
})->with([['unverified', true], ['restricted', true], ['suspended', false], ['banned', false], ['pendingDeletion', false]]);

it('ignores target and privileged fields in the deletion form', function () {
    $actor = User::factory()->create();
    $other = User::factory()->create();
    $this->actingAs($actor)->delete('/settings/danger-zone', [
        'confirmation' => true, 'current_password' => 'password', 'user_id' => $other->id, 'role' => 'super_admin',
        'status' => 'active', 'deletion_requested_at' => now()->subYear()->toIso8601String(),
    ])->assertRedirect('/login');
    expect($actor->refresh()->role)->toBe(Role::User)->and($actor->deletion_requested_at?->equalTo(now()))->toBeTrue()
        ->and($other->refresh()->status)->toBe(UserStatus::Active)->and($other->deletion_requested_at)->toBeNull();
});

it('keeps deletion reservations blocked beyond ninety days with public username limits intact', function () {
    UsernameHistory::factory()->permanent()->create(['username' => 'old_chief', 'released_at' => now()->subYear()]);
    expect(Validator::make(['username' => 'old_chief'], ['username' => UsernameFieldRules::forRegistration()])->fails())->toBeTrue()
        ->and(Validator::make(['username' => 'deleted_user_'.str_repeat('a', 26)], ['username' => UsernameFieldRules::forRegistration()])->fails())->toBeTrue();
});

it('enforces CSRF on account deletion', function () {
    $user = User::factory()->create();
    $this->app['env'] = 'production';
    $this->actingAs($user)
        ->delete('/settings/danger-zone', ['confirmation' => true, 'current_password' => 'password'])->assertStatus(419);
    expect($user->refresh()->status)->toBe(UserStatus::Active);
});

it('rolls registration back when a permanent reservation appears during the insert', function () {
    $owner = User::factory()->create();
    $started = RegistrationGuard::startToken();
    $this->travel((int) config('platform.auth.register_min_seconds') + 1)->seconds();
    Event::listen('eloquent.created: '.User::class, function (User $user) use ($owner): void {
        if ($user->username === 'released_chief') {
            UsernameHistory::factory()->permanent()->create(['user_id' => $owner->id, 'username' => $user->username]);
        }
    });
    expect(fn () => app(RegistrationService::class)->submit(
        new RegistrationData('new@example.com', 'released_chief', 'password'),
        '', $started, '127.0.0.1',
    ))->toThrow(ValidationException::class);
    expect(User::query()->where('email', 'new@example.com')->exists())->toBeFalse();
});
