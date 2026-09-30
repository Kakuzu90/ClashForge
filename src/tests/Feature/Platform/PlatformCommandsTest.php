<?php

use App\Support\Health\HealthChecker;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Storage;

it('records storage as ok when the bucket answers', function () {
    Storage::fake((string) config('media.disk'));

    $this->artisan('platform:check-health')->assertSuccessful();

    expect(Cache::get(HealthChecker::STORAGE_KEY))->toBe('ok');
});

it('probes with a single existence check on the configured key', function () {
    $disk = Mockery::mock(Storage::fake((string) config('media.disk')))->makePartial();
    Storage::set((string) config('media.disk'), $disk);

    $this->artisan('platform:check-health')->assertSuccessful();

    $disk->shouldHaveReceived('fileExists')->with(config('platform.health.storage_probe_key'))->once();
    $disk->shouldNotHaveReceived('exists');
    $disk->shouldNotHaveReceived('put');
});

it('records storage as down when the bucket errors', function () {
    $disk = Mockery::mock(Storage::fake((string) config('media.disk')))->makePartial();
    $disk->shouldReceive('fileExists')->andThrow(new RuntimeException('connection refused'));
    Storage::set((string) config('media.disk'), $disk);

    $this->artisan('platform:check-health')->assertFailed();

    expect(Cache::get(HealthChecker::STORAGE_KEY))->toBe('down');
});

it('writes the scheduler heartbeat', function () {
    Date::setTestNow('2026-09-30 12:00:00');

    $this->artisan('platform:heartbeat')->assertSuccessful();

    expect(Cache::get(HealthChecker::HEARTBEAT_KEY))->toBe(Date::now()->getTimestamp());

    // Stored with a TTL (specs/21 bans forever), far longer than the staleness window.
    $this->travel((int) config('platform.health.heartbeat_ttl') + 1)->seconds();
    expect(Cache::get(HealthChecker::HEARTBEAT_KEY))->toBeNull()
        ->and(config('platform.health.heartbeat_ttl'))->toBeGreaterThan(config('platform.health.heartbeat_max_age'));
});

it('schedules the heartbeat every minute and the health probe every five', function () {
    $events = collect(app(Schedule::class)->events());
    $heartbeat = $events->first(fn ($e) => str_contains($e->command, 'platform:heartbeat'));
    $probe = $events->first(fn ($e) => str_contains($e->command, 'platform:check-health'));

    expect($heartbeat->expression)->toBe('* * * * *')
        ->and($heartbeat->withoutOverlapping)->toBeTrue()
        ->and($heartbeat->expiresAt)->toBe(5)
        ->and($heartbeat->onOneServer)->toBeTrue()
        ->and($probe->expression)->toBe('*/5 * * * *')
        ->and($probe->withoutOverlapping)->toBeTrue()
        ->and($probe->expiresAt)->toBe(10)
        ->and($probe->onOneServer)->toBeTrue()
        ->and($probe->runInBackground)->toBeTrue();
});
