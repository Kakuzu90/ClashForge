<?php

use App\Domain\Operations\Enums\SchedulerState;
use App\Domain\Operations\Queries\SchedulerStatusQuery;
use App\Support\Health\HealthChecker;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Date;

// specs/20 §6 "Scheduler heartbeat": no `schedule:run` in `platform.health.heartbeat_max_age`.

beforeEach(fn () => Date::setTestNow('2026-10-02 12:00:00'));

it('reads no heartbeat as unknown, not stopped', function () {
    expect(app(SchedulerStatusQuery::class)->status()->toArray())->toBe([
        'state' => SchedulerState::Unknown->value,
        'lastBeatAt' => null,
        'ageSeconds' => null,
        'maxAgeSeconds' => (int) config('platform.health.heartbeat_max_age'),
    ]);
});

it('is running up to the age line and stopped past it', function () {
    $maxAge = (int) config('platform.health.heartbeat_max_age');

    Cache::put(HealthChecker::HEARTBEAT_KEY, now()->subSeconds($maxAge)->getTimestamp());
    expect(app(SchedulerStatusQuery::class)->status())
        ->state->toBe(SchedulerState::Running)
        ->ageSeconds->toBe($maxAge)
        ->lastBeatAt->toBe(now()->subSeconds($maxAge)->toIso8601String());

    Cache::put(HealthChecker::HEARTBEAT_KEY, now()->subSeconds($maxAge + 1)->getTimestamp());
    expect(app(SchedulerStatusQuery::class)->status()->state)->toBe(SchedulerState::Stopped);
});

it('reads the beat the scheduler writes', function () {
    $this->artisan('platform:heartbeat')->assertSuccessful();

    expect(app(SchedulerStatusQuery::class)->status())->state->toBe(SchedulerState::Running)->ageSeconds->toBe(0);
});
