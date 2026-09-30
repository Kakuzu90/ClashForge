<?php

use App\Support\Health\HealthChecker;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    Date::setTestNow('2026-09-30 12:00:00');
    Cache::put(HealthChecker::STORAGE_KEY, 'ok', 900);
    Cache::forever(HealthChecker::HEARTBEAT_KEY, Date::now()->getTimestamp());
});

it('reports ok when every check passes', function () {
    $this->getJson('/health')
        ->assertOk()
        ->assertHeader('Cache-Control', 'no-store, private')
        ->assertExactJson([
            'status' => 'ok',
            'checks' => ['database' => 'ok', 'queue' => 'ok', 'storage' => 'ok', 'scheduler' => 'ok'],
        ]);
});

it('reports unknown, not down, when no fresh state exists yet', function () {
    Cache::flush();

    $this->getJson('/health')
        ->assertOk()
        ->assertJsonPath('status', 'ok')
        ->assertJsonPath('checks.storage', 'unknown')
        ->assertJsonPath('checks.scheduler', 'unknown');
});

it('returns 503 when storage was found down', function () {
    Cache::put(HealthChecker::STORAGE_KEY, 'down', 900);

    $this->getJson('/health')->assertServiceUnavailable()->assertJsonPath('status', 'down')->assertJsonPath('checks.storage', 'down');
});

it('returns 503 when the scheduler heartbeat is stale', function () {
    Cache::forever(HealthChecker::HEARTBEAT_KEY, Date::now()->subSeconds((int) config('platform.health.heartbeat_max_age') + 1)->getTimestamp());

    $this->getJson('/health')->assertServiceUnavailable()->assertJsonPath('checks.scheduler', 'down');
});

it('returns 503 when the database is unreachable', function () {
    $original = config('database.default');
    config([
        'database.connections.broken' => ['driver' => 'sqlite', 'database' => '/nonexistent/dir/db.sqlite'],
        'database.default' => 'broken',
        // Production shape: the default cache store is the database, so the limiter breaks too.
        'platform.health.rate_limit_store' => 'database',
    ]);

    try {
        $this->getJson('/health')->assertServiceUnavailable()->assertJsonPath('checks.database', 'down')->assertJsonPath('checks.queue', 'down');
    } finally {
        // Hand the test database back so RefreshDatabase can roll back.
        config(['database.default' => $original]);
        DB::purge('broken');
    }
});

it('returns 503 when only the jobs table is unreachable', function () {
    config(['queue.connections.database.table' => 'no_such_jobs_table']);

    $this->getJson('/health')->assertServiceUnavailable()->assertJsonPath('checks.database', 'ok')->assertJsonPath('checks.queue', 'down');
});

it('reads a queue as degraded when its oldest job waits too long, without failing the probe', function () {
    DB::table('jobs')->insert([
        'queue' => 'high',
        'payload' => '{}',
        'attempts' => 0,
        'reserved_at' => null,
        'available_at' => Date::now()->subSeconds((int) config('platform.health.queue_max_wait.high') + 1)->getTimestamp(),
        'created_at' => Date::now()->getTimestamp(),
    ]);

    $this->getJson('/health')->assertOk()->assertJsonPath('status', 'degraded')->assertJsonPath('checks.queue', 'degraded');
});

it('only fails on checks listed as required', function () {
    config(['platform.health.required' => ['database', 'queue']]);
    Cache::put(HealthChecker::STORAGE_KEY, 'down', 900);

    $this->getJson('/health')->assertOk()->assertJsonPath('status', 'degraded');
});

it('does not fail on a stale heartbeat when the scheduler is not required', function () {
    config(['platform.health.required' => ['database', 'queue', 'storage']]);
    Cache::put(HealthChecker::HEARTBEAT_KEY, Date::now()->subHour()->getTimestamp(), 60);

    $this->getJson('/health')->assertOk()->assertJsonPath('status', 'degraded')->assertJsonPath('checks.scheduler', 'down');
});

it('starts no session and sets no cookies', function () {
    $response = $this->get('/health');

    expect($response->headers->getCookies())->toBe([]);
});

it('replaces the framework /up route', function () {
    $this->get('/up')->assertNotFound();
});
