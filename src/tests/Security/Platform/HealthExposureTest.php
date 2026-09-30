<?php

use App\Support\Health\HealthChecker;
use Illuminate\Support\Facades\Cache;

// /health is public: it may say that something is down, never why, where or which version.

it('exposes only status words', function () {
    Cache::put(HealthChecker::STORAGE_KEY, 'down', 900);

    $json = $this->getJson('/health')->json();

    expect(array_keys($json))->toBe(['status', 'checks'])
        ->and(array_unique(array_values($json['checks'])))->each->toBeIn(['ok', 'degraded', 'down', 'unknown']);

    $body = json_encode($json);
    foreach ([config('database.connections.pgsql.host'), config('app.key'), PHP_VERSION, app()->version(), 'Exception'] as $secret) {
        expect($body)->not->toContain((string) $secret);
    }
});

it('is rate limited per IP', function () {
    $limit = (int) config('platform.health.rate_limit_per_minute');

    for ($i = 0; $i < $limit; $i++) {
        $this->get('/health');
    }

    $this->get('/health')->assertTooManyRequests();
});
