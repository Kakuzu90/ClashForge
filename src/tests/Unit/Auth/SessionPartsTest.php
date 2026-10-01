<?php

use App\Domain\Auth\Services\SessionService;
use App\Domain\Auth\Support\CountryName;
use App\Domain\Auth\Support\DeviceLabel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Tests\TestCase;

uses(TestCase::class);

it('labels devices by browser family and OS', function (string $userAgent, string $label) {
    expect(DeviceLabel::fromUserAgent($userAgent))->toBe($label);
})->with([
    'chrome on windows' => ['Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/129.0.0.0 Safari/537.36', 'Chrome on Windows'],
    'edge on windows' => ['Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/129.0.0.0 Safari/537.36 Edg/129.0.0.0', 'Edge on Windows'],
    'safari on macos' => ['Mozilla/5.0 (Macintosh; Intel Mac OS X 14_6) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.0 Safari/605.1.15', 'Safari on macOS'],
    'safari on iphone' => ['Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.0 Mobile/15E148 Safari/604.1', 'Safari on iOS'],
    'chrome on ipad' => ['Mozilla/5.0 (iPad; CPU OS 18_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/129.0 Mobile/15E148 Safari/604.1', 'Chrome on iPadOS'],
    'samsung on android' => ['Mozilla/5.0 (Linux; Android 14; SM-S918B) AppleWebKit/537.36 (KHTML, like Gecko) SamsungBrowser/26.0 Chrome/122.0 Mobile Safari/537.36', 'Samsung Internet on Android'],
    'firefox on linux' => ['Mozilla/5.0 (X11; Linux x86_64; rv:131.0) Gecko/20100101 Firefox/131.0', 'Firefox on Linux'],
    'opera on chromeos' => ['Mozilla/5.0 (X11; CrOS x86_64 14541.0.0) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/129.0 Safari/537.36 OPR/114.0', 'Opera on ChromeOS'],
    'unknown' => ['curl/8.7.1', 'Unknown device'],
    'empty' => ['', 'Unknown device'],
]);

it('names countries and reads only real codes from the CDN header, through a trusted proxy only', function () {
    $request = fn (?string $value) => tap(Request::create('/'), fn (Request $r) => $value === null ? null : $r->headers->set('CF-IPCountry', $value));

    expect(CountryName::fromRequest($request('DE')))->toBeNull();

    Request::setTrustedProxies(['127.0.0.1'], Request::HEADER_X_FORWARDED_FOR);

    expect(CountryName::of('DE'))->toBe('Germany')
        ->and(CountryName::of(null))->toBeNull()
        ->and(CountryName::fromRequest($request('ph')))->toBe('PH')
        ->and(CountryName::fromRequest($request('XX')))->toBeNull()
        ->and(CountryName::fromRequest($request('T1')))->toBeNull()
        ->and(CountryName::fromRequest($request(null)))->toBeNull();

    Request::setTrustedProxies([], Request::HEADER_X_FORWARDED_FOR);
});

it('ends a session at the absolute lifetime, not before', function () {
    Date::setTestNow('2026-10-31 12:00:00');
    $days = (int) config('platform.auth.absolute_session_days');
    $sessions = new SessionService;

    expect($sessions->pastAbsoluteLifetime(Date::now()->subDays($days)->addSecond()->getTimestamp()))->toBeFalse()
        ->and($sessions->pastAbsoluteLifetime(Date::now()->subDays($days)->getTimestamp()))->toBeTrue();
});

it('derives a stable key from a session id that is not the id', function () {
    $id = str_repeat('a', 40);

    expect(SessionService::keyOf($id))->toBe(SessionService::keyOf($id))
        ->toMatch('/^[0-9a-f]{32}$/')
        ->not->toBe(SessionService::keyOf(str_repeat('b', 40)))
        ->and(str_contains($id, SessionService::keyOf($id)))->toBeFalse();
});
