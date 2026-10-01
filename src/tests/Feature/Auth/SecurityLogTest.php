<?php

use App\Models\User;
use App\Support\Privacy\IpHash;
use Illuminate\Support\Facades\Password;
use Tests\Support\Auth\CapturesSecurityLog;
use Tests\Support\Auth\FakesHibp;

// specs/11 §3: sign-ins, failures, resets and limiter breaches reach the `security` channel with a
// ULID and a hashed IP, and nothing the user typed.

uses(CapturesSecurityLog::class, FakesHibp::class);

beforeEach(function () {
    $this->captureSecurityLog();
    // Signed in before, so this browser counts as a new device (an account's first sign-in does not).
    $this->user = User::factory()->create(['email' => 'chief@example.com', 'password' => 'a-long-password', 'last_login_at' => now()->subDay()]);
});

function messages(array $events): array
{
    return array_column($events, 'message');
}

it('logs a failed and a successful sign-in with the ULID and a hashed IP', function () {
    $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.9'])->post('/login', ['email' => 'chief@example.com', 'password' => 'not-the-password']);
    $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.9'])->post('/login', ['email' => 'chief@example.com', 'password' => 'a-long-password']);

    $events = $this->securityEvents();
    // A first sign-in from this browser also logs the new device (P1-05).
    expect(messages($events))->toBe(['auth.login_failed', 'auth.new_device', 'auth.login'])
        ->and($events[0]['context'])->toBe(['user' => $this->user->ulid, 'ip_hash' => IpHash::of('203.0.113.9')])
        ->and($events[2]['context'])->toMatchArray(['user' => $this->user->ulid, 'remember' => false]);
});

it('logs a failure for an unknown email without the email', function () {
    $this->post('/login', ['email' => 'nobody@example.com', 'password' => 'not-the-password']);

    $events = $this->securityEvents();
    expect(messages($events))->toBe(['auth.login_failed'])
        ->and($events[0]['context']['user'])->toBeNull()
        ->and(json_encode($events))->not->toContain('nobody@example.com')
        ->and(json_encode($events))->not->toContain('not-the-password');
});

it('logs sign-out and password resets', function () {
    $this->fakeHibp();
    $this->actingAs($this->user)->post('/logout');
    $token = Password::createToken($this->user);

    $this->post('/reset-password', ['token' => $token, 'email' => 'chief@example.com', 'password' => 'brand-new-password', 'password_confirmation' => 'brand-new-password']);

    expect(messages($this->securityEvents()))->toContain('auth.logout', 'auth.password_reset');
});

it('logs limiter breaches', function () {
    foreach (range(1, (int) config('platform.auth.login_per_minute') + 1) as $attempt) {
        $this->post('/login', ['email' => 'chief@example.com', 'password' => 'not-the-password']);
    }

    expect(messages($this->securityEvents()))->toContain('auth.rate_limited');
});
