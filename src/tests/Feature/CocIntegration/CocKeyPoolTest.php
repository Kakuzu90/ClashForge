<?php

use App\Domain\CocIntegration\Services\CocKeyPool;
use Illuminate\Support\Facades\Date;
use Tests\Support\Coc\InteractsWithCoc;

uses(InteractsWithCoc::class);

beforeEach(function () {
    Date::setTestNow('2026-10-02 12:00:00');
    $this->useHttpCoc(['token-a', 'token-b', 'token-c', 'token-a']);
    $this->captureAppLog();
    $this->pool = app(CocKeyPool::class);
});

it('loads each configured token once, identified by a hash prefix', function () {
    $ids = array_map(fn ($k) => $k->id, $this->pool->keys());

    expect($ids)->toHaveCount(3)
        ->and($ids[0])->toBe(substr(hash('sha256', 'token-a'), 0, (int) config('coc.key_pool.id_length')));
});

it('rotates over the healthy keys', function () {
    $ids = collect(range(1, 6))->map(fn () => $this->pool->next()?->id)->all();

    expect(array_count_values($ids))->each->toBe(2);
});

it('skips unhealthy keys and the keys already tried', function () {
    [$a, $b, $c] = $this->pool->keys();
    $this->pool->markUnhealthy($a, 'accessDenied');

    $seen = collect(range(1, 4))->map(fn () => $this->pool->next()?->id)->unique()->sort()->values()->all();

    expect($seen)->toEqual(collect([$b->id, $c->id])->sort()->values()->all())
        ->and($this->pool->next([$b->id])?->id)->toBe($c->id)
        ->and($this->pool->next([$b->id, $c->id]))->toBeNull();
});

it('brings a key back after unhealthy_ttl at most (owner decision 2026-10-02)', function () {
    [$a] = $this->pool->keys();
    $this->pool->markUnhealthy($a, 'accessDenied');

    $this->travel((int) config('coc.key_pool.unhealthy_ttl') - 1)->seconds();
    expect($this->pool->status()->keys[0]->healthy)->toBeFalse();

    $this->travel(2)->seconds();
    expect($this->pool->status()->keys[0]->healthy)->toBeTrue();
});

it('logs a key going unhealthy once per outage, and all keys down once', function () {
    foreach ($this->pool->keys() as $key) {
        $this->pool->markUnhealthy($key, 'accessDenied.invalidIp');
        $this->pool->markUnhealthy($key, 'accessDenied.invalidIp');
    }

    $messages = array_count_values($this->appLogMessages());
    expect($messages['coc.key_unhealthy'])->toBe(3)
        ->and($messages['coc.keys_all_unhealthy'])->toBe(1);
});

it('reports the pool by key id with the reason and time', function () {
    [$a] = $this->pool->keys();
    $this->pool->markUnhealthy($a, 'accessDenied.invalidIp');

    $status = $this->pool->status();

    expect($status->total)->toBe(3)
        ->and($status->healthy)->toBe(2)
        ->and($status->keys[0]->reason)->toBe('accessDenied.invalidIp')
        ->and($status->keys[0]->unhealthySince?->equalTo(Date::now()))->toBeTrue()
        ->and($status->keys[1]->reason)->toBeNull();
});

it('keeps an unknown API reason out of the status', function () {
    [$a] = $this->pool->keys();
    $this->pool->markUnhealthy($a, '<script>alert(1)</script>');

    expect($this->pool->status()->keys[0]->reason)->toBe('accessDenied');
});

it('clears a marker when the key works again', function () {
    [$a] = $this->pool->keys();
    $this->pool->markUnhealthy($a, 'accessDenied');
    $this->pool->markHealthy($a);
    $this->pool->markHealthy($a);

    expect($this->pool->status()->healthy)->toBe(3)
        ->and(array_count_values($this->appLogMessages())['coc.key_healthy'])->toBe(1);
});
