<?php

use App\Domain\Auth\Enums\Role;
use App\Domain\Auth\Enums\UserStatus;
use App\Domain\Auth\Notifications\NewSignInNotification;
use App\Domain\Auth\Services\PasswordChangeService;
use App\Domain\Auth\Services\SessionService;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\Support\Auth\FakesHibp;
use Tests\Support\Auth\InteractsWithBrowsers;

// specs/04 §1, §3 and specs/11 on the security settings: own sessions only, privileged fields
// ignored, writes follow account status, and the page never carries a replayable session value.

uses(FakesHibp::class, InteractsWithBrowsers::class);

beforeEach(function () {
    $this->useDatabaseSessions();
    $this->fakeHibp();
    Notification::fake();
});

const NEW_PASSWORD = ['current_password' => 'password', 'password' => 'a-brand-new-passphrase', 'password_confirmation' => 'a-brand-new-passphrase'];

it('never lets one account sign out another account\'s session', function () {
    $victim = User::factory()->create(['password' => 'password']);
    $attacker = User::factory()->create(['password' => 'password']);
    $this->signInBrowser($victim);
    $attackerBrowser = $this->signInBrowser($attacker);
    $victimRow = DB::table('sessions')->where('user_id', $victim->id)->first();

    $this->browser($attackerBrowser)->delete('/settings/security/sessions/'.SessionService::keyOf($victimRow->id))->assertNotFound();
    $this->browser($attackerBrowser)->delete('/settings/security/sessions');

    expect(DB::table('sessions')->where('id', $victimRow->id)->exists())->toBeTrue();
});

it('refuses a raw session id in place of the key', function () {
    $user = User::factory()->create(['password' => 'password']);
    $this->signInBrowser($user);
    $browser = $this->signInBrowser($user);
    $other = DB::table('sessions')->where('user_id', $user->id)->orderBy('created_at')->first();

    $this->browser($browser)->delete("/settings/security/sessions/{$other->id}")->assertNotFound();
    expect(DB::table('sessions')->where('id', $other->id)->exists())->toBeTrue();
});

it('puts no session id, IP, user agent or payload in the page', function () {
    $user = User::factory()->create(['password' => 'password', 'email' => 'chief@example.com']);
    $browser = $this->signInBrowser($user, ['User-Agent' => 'Mozilla/5.0 (X11; Linux x86_64) Firefox/131.0']);
    $row = DB::table('sessions')->where('user_id', $user->id)->first();

    $html = (string) $this->browser($browser)->get('/settings/security')->getContent();
    $json = (string) $this->loadDeferred($browser, '/settings/security', 'Settings/Security', 'sessions')->getContent();

    foreach ([$html, $json] as $body) {
        expect($body)->not->toContain($row->id)
            ->not->toContain((string) $row->ip_hash)
            ->not->toContain('127.0.0.1')
            ->not->toContain('X11; Linux')
            ->not->toContain('chief@example.com');
    }
});

it('ignores privileged fields posted with a password change or a revoke', function () {
    $user = User::factory()->restricted()->create(['password' => 'password']);

    $this->actingAs($user)->put('/settings/security/password', [...NEW_PASSWORD, 'role' => 'admin', 'status' => 'active', 'email_verified_at' => null, 'user_id' => 999])
        ->assertSessionHasNoErrors();
    $this->actingAs($user)->delete('/settings/security/sessions', ['role' => 'admin', 'status' => 'active']);

    $user->refresh();
    expect($user->role)->toBe(Role::User)
        ->and($user->status)->toBe(UserStatus::Restricted)
        ->and($user->email_verified_at)->not->toBeNull();
});

it('lets security writes follow account status', function (string $state, bool $allowed) {
    $user = User::factory()->{$state}()->create(['password' => 'password']);

    $change = $this->actingAs($user)->put('/settings/security/password', NEW_PASSWORD);
    $revoke = $this->actingAs($user)->delete('/settings/security/sessions');

    expect(Hash::check('a-brand-new-passphrase', $user->refresh()->password))->toBe($allowed);
    if (! $allowed) {
        expect($change->getStatusCode())->toBe(403)
            ->and($revoke->getStatusCode())->toBe(403);
    }
})->with([
    'active' => ['admin', true],
    'restricted' => ['restricted', true],
    'unverified' => ['unverified', true],
    'suspended' => ['suspended', false],
    'pending deletion' => ['pendingDeletion', false],
]);

it('keeps the security page readable to a suspended account', function () {
    $this->actingAs(User::factory()->suspended()->create())->get('/settings/security')->assertOk();
});

it('authorizes in the services too, not only in the middleware', function () {
    $user = User::factory()->create(['password' => 'password']);
    $suspended = User::factory()->suspended()->create(['password' => 'password']);

    expect(Gate::forUser($user)->allows('manageSessions', $user))->toBeTrue()
        ->and(Gate::forUser(User::factory()->superAdmin()->create())->allows('manageSessions', $user))->toBeFalse()
        ->and(Gate::forUser(User::factory()->superAdmin()->create())->allows('changePassword', $user))->toBeFalse();

    expect(fn () => app(SessionService::class)->revokeOthers($suspended, null))->toThrow(AuthorizationException::class)
        ->and(fn () => app(PasswordChangeService::class)->change($suspended, 'password', 'a-brand-new-passphrase', null))->toThrow(AuthorizationException::class);
    expect(Hash::check('password', $suspended->refresh()->password))->toBeTrue();
});

it('never hands out a remember-me cookie when signing out other devices', function () {
    $user = User::factory()->create(['password' => 'password']);
    $browser = $this->signInBrowser($user);
    $recaller = auth()->guard('web')->getRecallerName();

    // A hijacked session without remember-me sends a garbage recaller, hoping for a real one back.
    $response = $this->browser([...$browser, $recaller => 'garbage'])->delete('/settings/security/sessions');

    $issued = collect($response->headers->getCookies())->first(fn ($cookie) => $cookie->getName() === $recaller);
    expect($issued === null || $issued->isCleared() || $issued->getExpiresTime() < time())->toBeTrue();
});

it('cannot be fooled by a tampered known_devices cookie', function () {
    $user = User::factory()->create(['password' => 'password']);

    // A plaintext list the browser wrote itself does not decrypt, so it counts as unknown.
    $this->signInBrowser($user, cookies: ['known_devices' => json_encode([$user->ulid])]);

    Notification::assertSentTo($user, NewSignInNotification::class);
});
