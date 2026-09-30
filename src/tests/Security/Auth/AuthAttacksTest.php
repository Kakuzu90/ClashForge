<?php

use App\Domain\Auth\Notifications\ResetPasswordNotification;
use App\Domain\Auth\Services\AuthenticationService;
use App\Http\Responses\Auth\PasswordResetFailedResponse;
use App\Http\Responses\Auth\PasswordResetLinkResponse;
use App\Models\User;
use Illuminate\Database\Eloquent\MassAssignmentException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\Support\Auth\FakesHibp;

// specs/11 §4 auth suite: enumeration, fixation, reset-token reuse, limiters, mass assignment.

uses(FakesHibp::class);

beforeEach(function () {
    $this->user = User::factory()->create(['email' => 'chief@example.com', 'password' => 'a-long-password']);
});

describe('account enumeration', function () {
    it('answers a wrong password and an unknown email identically', function () {
        $wrong = $this->from('/login')->post('/login', ['email' => 'chief@example.com', 'password' => 'not-the-password']);
        $wrongErrors = session('errors')->getBag('default')->toArray();

        $this->flushSession();

        $unknown = $this->from('/login')->post('/login', ['email' => 'nobody@example.com', 'password' => 'not-the-password']);
        $unknownErrors = session('errors')->getBag('default')->toArray();

        expect($wrong->getStatusCode())->toBe($unknown->getStatusCode())
            ->and($wrong->headers->get('Location'))->toBe($unknown->headers->get('Location'))
            ->and($wrongErrors)->toBe($unknownErrors)
            ->and($wrongErrors)->toBe(['email' => [__('auth.failed')]]);
    });

    it('runs one password hash check for an unknown email, as for a known one', function () {
        Cache::put('auth.timing_hash', Hash::make('primed'), now()->addDay());
        Hash::shouldReceive('needsRehash')->andReturnFalse();
        Hash::shouldReceive('check')->once()->andReturnFalse();
        Hash::shouldReceive('make')->never();

        expect(app(AuthenticationService::class)->attempt('nobody@example.com', 'whatever-password'))->toBeNull();
    });

    it('answers a reset request identically for unknown and known emails, and mails only the known one', function () {
        Notification::fake();

        $known = $this->from('/forgot-password')->post('/forgot-password', ['email' => 'chief@example.com']);
        $knownStatus = session('status');
        $this->flushSession();
        $unknown = $this->from('/forgot-password')->post('/forgot-password', ['email' => 'nobody@example.com']);

        expect($known->headers->get('Location'))->toBe($unknown->headers->get('Location'))
            ->and($knownStatus)->toBe(session('status'))
            ->and($knownStatus)->toBe(PasswordResetLinkResponse::message());
        Notification::assertSentTimes(ResetPasswordNotification::class, 1);
    });

    it('caps reset emails per account without changing the answer', function () {
        Notification::fake();

        $this->from('/forgot-password')->post('/forgot-password', ['email' => 'chief@example.com']);
        $this->from('/forgot-password')->post('/forgot-password', ['email' => 'chief@example.com'])
            ->assertSessionHas('status', PasswordResetLinkResponse::message())
            ->assertSessionHasNoErrors();

        // The broker's one-per-minute throttle holds back the second email, silently.
        Notification::assertSentTimes(ResetPasswordNotification::class, 1);
    });

});

it('issues a new session id on sign-in (fixation)', function () {
    $this->get('/login');
    $before = session()->getId();

    $this->post('/login', ['email' => 'chief@example.com', 'password' => 'a-long-password']);

    expect(session()->getId())->not->toBe($before);
});

it('refuses a reset link the second time', function () {
    $this->fakeHibp();
    $token = Password::createToken($this->user);
    $payload = ['token' => $token, 'email' => 'chief@example.com', 'password' => 'brand-new-password', 'password_confirmation' => 'brand-new-password'];

    $this->post('/reset-password', $payload)->assertRedirect('/login');
    $this->post('/reset-password', [...$payload, 'password' => 'another-new-password', 'password_confirmation' => 'another-new-password'])
        ->assertSessionHasErrors(['email' => PasswordResetFailedResponse::MESSAGE]);

    expect(Hash::check('brand-new-password', $this->user->refresh()->password))->toBeTrue();
});

describe('named limiters (specs/04 §4)', function () {
    it('stops sign-in attempts after the per-minute limit, even with the right password', function () {
        $limit = (int) config('platform.auth.login_per_minute');

        foreach (range(1, $limit) as $attempt) {
            $this->post('/login', ['email' => 'chief@example.com', 'password' => 'not-the-password']);
        }

        $this->post('/login', ['email' => 'chief@example.com', 'password' => 'a-long-password'])
            ->assertSessionHasErrors(['email' => 'Too many attempts. Try again in 60 seconds.']);
        $this->assertGuest();
    });

    it('keys the sign-in limit on ip and email', function () {
        foreach (range(1, (int) config('platform.auth.login_per_minute')) as $attempt) {
            $this->post('/login', ['email' => 'chief@example.com', 'password' => 'not-the-password']);
        }

        User::factory()->create(['email' => 'other@example.com', 'password' => 'a-long-password']);
        $this->post('/login', ['email' => 'other@example.com', 'password' => 'a-long-password']);
        $this->assertAuthenticated();
    });

    it('applies the hourly sign-in limit on top of the per-minute one', function () {
        config(['platform.auth.login_per_minute' => 100, 'platform.auth.login_per_hour' => 2]);

        $this->post('/login', ['email' => 'chief@example.com', 'password' => 'x-wrong-1']);
        $this->post('/login', ['email' => 'chief@example.com', 'password' => 'x-wrong-2']);

        $this->post('/login', ['email' => 'chief@example.com', 'password' => 'a-long-password'])->assertSessionHasErrors('email');
        $this->assertGuest();
    });

    it('caps sign-in attempts per IP however the email changes', function () {
        config(['platform.auth.login_per_ip_per_minute' => 3]);

        foreach (range(1, 3) as $i) {
            $this->post('/login', ['email' => "random{$i}@example.com", 'password' => 'whatever-password']);
        }

        $this->post('/login', ['email' => 'chief@example.com', 'password' => 'a-long-password'])->assertSessionHasErrors('email');
        $this->assertGuest();
    });

    it('caps reset-link requests per IP however the email changes', function () {
        Notification::fake();
        config(['platform.auth.password_reset_per_ip_per_hour' => 2]);

        $this->post('/forgot-password', ['email' => 'one@example.com'])->assertSessionHasNoErrors();
        $this->post('/forgot-password', ['email' => 'two@example.com'])->assertSessionHasNoErrors();
        $this->post('/forgot-password', ['email' => 'three@example.com'])->assertSessionHasErrors('email');
    });

    it('limits reset-link requests per ip and email', function () {
        Notification::fake();

        foreach (range(1, (int) config('platform.auth.password_reset_per_hour')) as $request) {
            $this->post('/forgot-password', ['email' => 'chief@example.com'])->assertSessionHasNoErrors();
        }

        $this->post('/forgot-password', ['email' => 'chief@example.com'])->assertSessionHasErrors(['email' => 'Too many attempts. Try again in 60 minutes.']);
    });

    it('does not count typos on the new-password form against the reset limit', function () {
        $this->fakeHibp();
        $token = Password::createToken($this->user);

        foreach (range(1, 5) as $typo) {
            $this->post('/reset-password', ['token' => $token, 'email' => 'chief@example.com', 'password' => 'short', 'password_confirmation' => 'short'])
                ->assertSessionHasErrors('password')
                ->assertSessionDoesntHaveErrors('email');
        }
    });
});

it('refuses privileged fields on mass assignment', function (string $field, mixed $value) {
    expect(fn () => new User(['username' => 'sneaky', 'email' => 'sneaky@example.com', 'password' => 'a-long-password', $field => $value]))
        ->toThrow(MassAssignmentException::class);

    // Production discards instead of throwing; either way the value never lands.
    Model::preventSilentlyDiscardingAttributes(false);
    expect((new User([$field => $value]))->getAttributes()[$field] ?? null)->not->toBe($value);
    Model::preventSilentlyDiscardingAttributes();
})->with([
    'email_verified_at' => ['email_verified_at', '2026-01-01 00:00:00'],
    'remember_token' => ['remember_token', 'chosen'],
    'last_login_at' => ['last_login_at', '2026-01-01 00:00:00'],
    'last_login_ip_hash' => ['last_login_ip_hash', 'forged'],
    'ulid' => ['ulid', '01j000000000000000000000aa'],
    'role' => ['role', 'super_admin'],
    'status' => ['status', 'restricted'],
    'status_reason' => ['status_reason', 'forged'],
    'status_expires_at' => ['status_expires_at', '2026-01-01 00:00:00'],
]);
