<?php

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Auth\Data\UsernameFieldRules;
use App\Domain\Auth\Models\UsernameHistory;
use App\Domain\Auth\Services\UsernameChangeService;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

// FR-PROFILE-7 at the trust boundary: own account only, writes follow account status and need a
// verified email, privileged fields are ignored, password guesses share `password-confirm`, and a
// redirect from an old name answers like an unknown name when its target is hidden (specs/11).

beforeEach(fn () => Date::setTestNow('2026-10-01 12:00:00'));

it('lets username changes follow account status and email verification', function (string $state, bool $allowed) {
    $factory = User::factory();
    $user = ($state === 'active' ? $factory : $factory->{$state}())->create(['username' => 'chief']);

    $response = $this->actingAs($user)->put('/settings/profile/username', ['username' => 'new_chief', 'current_password' => 'password']);

    if ($allowed) {
        $response->assertSessionHasNoErrors();
        expect($user->refresh()->username)->toBe('new_chief');
    } else {
        $response->assertForbidden();
        expect($user->refresh()->username)->toBe('chief')->and(UsernameHistory::query()->count())->toBe(0);
    }
})->with([
    'active' => ['active', true],
    'admin' => ['admin', true],
    'restricted' => ['restricted', true],
    'unverified' => ['unverified', false],
    'suspended' => ['suspended', false],
    'pending deletion' => ['pendingDeletion', false],
]);

it('rolls the change back when another account\'s release of the name commits during the update', function () {
    $user = User::factory()->create(['username' => 'chief']);
    $other = User::factory()->create();
    Event::listen('eloquent.updated: '.User::class, function (User $updated) use ($other): void {
        if ($updated->username === 'released') {
            UsernameHistory::factory()->create(['user_id' => $other->id, 'username' => 'released', 'released_at' => Date::now()]);
        }
    });

    expect(fn () => app(UsernameChangeService::class)->change($user, 'released', 'password', null))
        ->toThrow(ValidationException::class, UsernameFieldRules::TAKEN);
    expect($user->refresh()->username)->toBe('chief')
        ->and($user->username_changed_at)->toBeNull()
        ->and(UsernameHistory::query()->where('user_id', $user->id)->exists())->toBeFalse()
        ->and(AuditLog::query()->count())->toBe(0);
});

it('answers "taken" when another account claims the name between the check and the update', function () {
    $user = User::factory()->create(['username' => 'chief']);
    Event::listen('eloquent.updating: '.User::class, function (User $updating): void {
        if ($updating->username === 'contested') {
            DB::table('users')->insert(['ulid' => strtolower((string) Str::ulid()), 'username' => 'contested', 'email' => 'other@example.com', 'password' => 'x', 'created_at' => now(), 'updated_at' => now()]);
        }
    });

    expect(fn () => app(UsernameChangeService::class)->change($user, 'contested', 'password', null))
        ->toThrow(ValidationException::class, UsernameFieldRules::TAKEN);
    expect($user->refresh()->username)->toBe('chief')
        ->and(UsernameHistory::query()->count())->toBe(0);
});

it('answers bytes that cannot be stored with a field error, not a database error', function (string $name) {
    $user = User::factory()->create(['username' => 'chief']);

    $this->actingAs($user)->from('/settings/profile')->put('/settings/profile/username', ['username' => $name, 'current_password' => 'password'])
        ->assertRedirect('/settings/profile')
        ->assertSessionHasErrors('username');
    expect($user->refresh()->username)->toBe('chief');
})->with(['invalid utf-8' => ["chief\xFF"], 'nul' => ["chi\0ef"]]);

it('authorizes the locked row, so a suspension that committed after the request started counts', function () {
    $user = User::factory()->create(['username' => 'chief']);
    User::query()->whereKey($user->id)->update(['status' => 'suspended', 'status_reason' => 'Harassment']);

    expect(fn () => app(UsernameChangeService::class)->change($user, 'new_chief', 'password', null))->toThrow(AuthorizationException::class)
        ->and($user->refresh()->username)->toBe('chief');
});

it('tells an unverified account why the change is closed', function () {
    $this->actingAs(User::factory()->unverified()->create())->get('/settings/profile')
        ->assertInertia(fn ($page) => $page->where('username.canChange', false)->where('username.needsVerifiedEmail', true));
});

it('authorizes in the service, for the account itself only', function () {
    $user = User::factory()->create();

    expect(Gate::forUser($user)->allows('changeUsername', $user))->toBeTrue()
        ->and(Gate::forUser(User::factory()->superAdmin()->create())->allows('changeUsername', $user))->toBeFalse();

    $suspended = User::factory()->suspended()->create(['username' => 'chief']);
    expect(fn () => app(UsernameChangeService::class)->change($suspended, 'new_chief', 'password', null))->toThrow(AuthorizationException::class)
        ->and($suspended->refresh()->username)->toBe('chief');
});

it('ignores privileged fields sent with the change', function () {
    $user = User::factory()->create(['username' => 'chief']);

    $this->actingAs($user)->put('/settings/profile/username', [
        'username' => 'new_chief',
        'current_password' => 'password',
        'username_changed_at' => '2020-01-01 00:00:00',
        'role' => 'super_admin',
        'status' => 'active',
        'user_id' => 999,
    ])->assertSessionHasNoErrors();

    $user->refresh();
    expect($user->username_changed_at?->equalTo(Date::now()))->toBeTrue()
        ->and($user->role->value)->toBe('user')
        ->and(UsernameHistory::query()->sole()->user_id)->toBe($user->id);
});

it('shares the password-guess limit with the other password forms', function () {
    $user = User::factory()->create(['username' => 'chief']);
    $limit = (int) config('platform.auth.password_confirm_per_minute');

    for ($i = 0; $i < $limit; $i++) {
        $this->actingAs($user)->put('/settings/profile/username', ['username' => 'new_chief', 'current_password' => 'wrong']);
    }

    $this->actingAs($user)->from('/settings/profile')->put('/settings/profile/username', ['username' => 'new_chief', 'current_password' => 'password'])
        ->assertSessionHasErrors('current_password');
    expect($user->refresh()->username)->toBe('chief');
});

it('answers an old name with a hidden target exactly like an unknown name', function () {
    $user = User::factory()->withPrivacy(['profile_visibility' => 'private'])->create(['username' => 'hidden_now']);
    UsernameHistory::factory()->create(['user_id' => $user->id, 'username' => 'hidden', 'released_at' => Date::now()->subDay()]);

    $hidden = $this->get('/u/hidden');
    $unknown = $this->get('/u/nobody_here');

    expect($hidden->getStatusCode())->toBe(404)
        ->and($hidden->headers->get('Location'))->toBeNull()
        ->and(str_replace('hidden', 'nobody_here', (string) $hidden->getContent()))->toBe((string) $unknown->getContent());
});
