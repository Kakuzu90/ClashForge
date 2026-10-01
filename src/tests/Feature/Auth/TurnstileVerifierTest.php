<?php

use App\Domain\Auth\Services\CloudflareTurnstileVerifier;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\Support\Auth\CapturesSecurityLog;

// FR-AUTH-11 and the owner decision of 2026-10-01: siteverify decides; unreachable fails open and
// is logged; a missing secret fails closed.

uses(CapturesSecurityLog::class);

beforeEach(fn () => $this->captureSecurityLog());

it('passes a token Cloudflare accepts, sending the secret, token and IP', function () {
    Http::fake(['challenges.cloudflare.com/*' => Http::response(['success' => true])]);

    expect((new CloudflareTurnstileVerifier)->passes('good-token', '203.0.113.9'))->toBeTrue();

    Http::assertSent(fn (Request $request) => $request->url() === config('services.turnstile.verify_url')
        && $request['secret'] === config('services.turnstile.secret')
        && $request['response'] === 'good-token'
        && $request['remoteip'] === '203.0.113.9');
});

it('refuses a token Cloudflare rejects, and logs the codes', function () {
    Http::fake(['challenges.cloudflare.com/*' => Http::response(['success' => false, 'error-codes' => ['timeout-or-duplicate']])]);

    expect((new CloudflareTurnstileVerifier)->passes('used-token', '203.0.113.9'))->toBeFalse();

    $line = collect($this->securityEvents())->firstWhere('message', 'auth.turnstile_failed');
    expect($line['context']['codes'])->toBe(['timeout-or-duplicate']);
});

it('refuses a missing or oversized token without asking', function (?string $token) {
    Http::fake();

    expect((new CloudflareTurnstileVerifier)->passes($token, null))->toBeFalse();

    Http::assertNothingSent();
})->with([null, '', str_repeat('x', 2049)]);

it('lets the form through when Cloudflare is down or slow, and says so', function (Closure $fake) {
    $fake();
    Log::spy();

    expect((new CloudflareTurnstileVerifier)->passes('token', null))->toBeTrue();

    Log::shouldHaveReceived('warning')->withArgs(fn (string $message) => $message === 'auth.turnstile_unavailable')->once();
})->with([
    'server error' => [fn () => Http::fake(['*' => Http::response('', 503)])],
    'timeout' => [fn () => Http::fake(['*' => fn () => throw new ConnectionException('timed out')])],
]);

it('fails closed without a secret', function () {
    config(['services.turnstile.secret' => null]);
    Http::fake();
    Log::spy();

    expect((new CloudflareTurnstileVerifier)->passes('token', null))->toBeFalse();

    Http::assertNothingSent();
    Log::shouldHaveReceived('error')->with('auth.turnstile_misconfigured')->once();
});

it('defaults to Cloudflare\'s always-pass test keys in tests, with a 3 s timeout', function () {
    expect(config('services.turnstile.site_key'))->toBe('1x00000000000000000000AA')
        ->and(config('services.turnstile.secret'))->toBe('1x0000000000000000000000000000000AA')
        ->and(config('services.turnstile.timeout'))->toBe(3);
});
