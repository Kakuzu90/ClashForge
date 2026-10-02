<?php

use App\Domain\CocIntegration\Services\CocKeyPool;
use App\Support\Health\HealthChecker;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\Support\Coc\InteractsWithCoc;

// specs/09 §3 startup check and key-pool health; specs/23 §5: an egress IP change is seen within
// one 5-minute run.

uses(InteractsWithCoc::class);

beforeEach(function () {
    Date::setTestNow('2026-10-02 12:00:00');
    Cache::put(HealthChecker::STORAGE_KEY, 'ok', 900);
    Cache::forever(HealthChecker::HEARTBEAT_KEY, Date::now()->getTimestamp());
    $this->captureAppLog();
});

function fakeKeyAnswers(object $test, array $byKey): void
{
    Http::fake(function (Request $request) use ($test, $byKey) {
        $token = substr($request->header('Authorization')[0], strlen('Bearer '));

        return $byKey[$token] === 200
            ? Http::response($test->cocFixtureBody('responses/locations.json'))
            : Http::response($test->cocFixtureBody('responses/error-invalidIp.json'), $byKey[$token]);
    });
}

it('reports ok with the fake driver outside production', function () {
    Http::fake();

    $this->artisan('coc:check-health')->assertSuccessful();

    expect(Cache::get(HealthChecker::COC_KEY))->toBe('ok');
    Http::assertNothingSent();
});

it('probes each key once with a cheap call and reports ok when all work', function () {
    $this->useHttpCoc(['key-one', 'key-two']);
    fakeKeyAnswers($this, ['key-one' => 200, 'key-two' => 200]);

    $this->artisan('coc:check-health')->assertSuccessful();

    expect(Cache::get(HealthChecker::COC_KEY))->toBe('ok');
    Http::assertSentCount(2);
    Http::assertSent(fn (Request $r): bool => $r->url() === config('coc.base_url').'/locations?limit=1');
});

it('reports degraded when some keys are refused, and marks them', function () {
    $this->useHttpCoc(['key-one', 'key-two']);
    fakeKeyAnswers($this, ['key-one' => 200, 'key-two' => 403]);

    $this->artisan('coc:check-health')->assertSuccessful();

    expect(Cache::get(HealthChecker::COC_KEY))->toBe('degraded')
        ->and(app(CocKeyPool::class)->status()->healthy)->toBe(1);
});

it('reports down and fails when every key is refused, without failing /health', function () {
    $this->useHttpCoc(['key-one', 'key-two']);
    fakeKeyAnswers($this, ['key-one' => 403, 'key-two' => 403]);

    $this->artisan('coc:check-health')->assertFailed();

    expect(Cache::get(HealthChecker::COC_KEY))->toBe('down')
        ->and($this->appLogMessages())->toContain('coc.keys_all_unhealthy');

    $this->getJson('/health')->assertOk()->assertJsonPath('status', 'degraded')->assertJsonPath('checks.coc', 'down');
});

it('reports degraded and leaves the markers alone when the API does not answer', function () {
    $this->useHttpCoc(['key-one']);
    app(CocKeyPool::class)->markUnhealthy(app(CocKeyPool::class)->keys()[0], 'accessDenied');
    Http::fake(['*' => Http::response('', 503)]);

    $this->artisan('coc:check-health')->assertSuccessful();

    expect(Cache::get(HealthChecker::COC_KEY))->toBe('degraded')
        ->and(app(CocKeyPool::class)->status()->healthy)->toBe(0);
});

it('puts a refused key back in rotation as soon as a probe succeeds', function () {
    $this->useHttpCoc(['key-one']);
    $pool = app(CocKeyPool::class);
    $pool->markUnhealthy($pool->keys()[0], 'accessDenied');
    fakeKeyAnswers($this, ['key-one' => 200]);

    $this->artisan('coc:check-health')->assertSuccessful();

    expect($pool->status()->healthy)->toBe(1)->and($this->appLogMessages())->toContain('coc.key_healthy');
});

it('reports down when the http driver has no keys', function () {
    $this->useHttpCoc([]);
    Http::fake();

    $this->artisan('coc:check-health')->assertFailed();

    expect(Cache::get(HealthChecker::COC_KEY))->toBe('down');
});

it('reports the fake driver in production as down', function () {
    $this->app['env'] = 'production';

    try {
        $this->artisan('coc:check-health')->assertFailed();
    } finally {
        $this->app['env'] = 'testing';
    }

    expect(Cache::get(HealthChecker::COC_KEY))->toBe('down')
        ->and($this->appLogMessages())->toContain('coc.fake_driver_in_production');
});

it('runs inside platform:check-health and records the result for /health', function () {
    Storage::fake((string) config('media.disk'));
    $this->useHttpCoc(['key-one']);
    fakeKeyAnswers($this, ['key-one' => 200]);

    $this->artisan('platform:check-health')->assertSuccessful();

    expect(Cache::get(HealthChecker::COC_KEY))->toBe('ok');
    $this->getJson('/health')->assertOk()->assertJsonPath('checks.coc', 'ok');
});

it('fails platform:check-health when no key works', function () {
    Storage::fake((string) config('media.disk'));
    $this->useHttpCoc(['key-one']);
    fakeKeyAnswers($this, ['key-one' => 403]);

    $this->artisan('platform:check-health')->assertFailed();
});

it('lets the recorded result lapse to unknown', function () {
    $this->artisan('coc:check-health')->assertSuccessful();

    $this->travel((int) config('platform.health.external_ttl') + 1)->seconds();
    Cache::forever(HealthChecker::HEARTBEAT_KEY, Date::now()->getTimestamp());
    Cache::put(HealthChecker::STORAGE_KEY, 'ok', 900);

    $this->getJson('/health')->assertOk()->assertJsonPath('status', 'ok')->assertJsonPath('checks.coc', 'unknown');
});

it('never makes the game API a required check (NFR-AVAIL-2)', function () {
    expect(config('platform.health.required'))->not->toContain('coc');
});
