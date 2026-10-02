<?php

use App\Domain\Operations\Queries\FailedJobsQuery;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

// specs/20 §5–6: every kept failure, one row per job class, with its queues.

beforeEach(fn () => Date::setTestNow('2026-10-02 12:00:00'));

function failedJob(string $at, string $queue = 'default', ?string $payload = null): void
{
    DB::table((string) config('queue.failed.table'))->insert([
        'uuid' => (string) Str::uuid(),
        'connection' => 'database',
        'queue' => $queue,
        'payload' => $payload ?? json_encode(['displayName' => 'App\\Jobs\\SendMail']),
        'exception' => 'RuntimeException: boom',
        'failed_at' => $at,
    ]);
}

it('groups by class across queues, most failures first', function () {
    failedJob(now()->subMinutes(10)->toDateTimeString(), 'high');
    failedJob(now()->subDays(3)->toDateTimeString(), 'low');
    failedJob(now()->subDays(1)->toDateTimeString(), 'high');
    failedJob(now()->subMinutes(5)->toDateTimeString(), 'sync', json_encode(['displayName' => 'App\\Jobs\\SyncAccount']));

    $report = app(FailedJobsQuery::class)->byClass();

    expect($report->total)->toBe(4)
        ->and($report->lastHour)->toBe(2)
        ->and($report->retentionDays)->toBe((int) config('platform.prune.failed_jobs_days'))
        ->and(array_map(fn ($c) => $c->toArray(), $report->classes))->toBe([
            [
                'name' => 'App\\Jobs\\SendMail',
                'queues' => ['high', 'low'],
                'count' => 3,
                'lastHour' => 1,
                'firstFailedAt' => now()->subDays(3)->toIso8601String(),
                'lastFailedAt' => now()->subMinutes(10)->toIso8601String(),
            ],
            [
                'name' => 'App\\Jobs\\SyncAccount',
                'queues' => ['sync'],
                'count' => 1,
                'lastHour' => 1,
                'firstFailedAt' => now()->subMinutes(5)->toIso8601String(),
                'lastFailedAt' => now()->subMinutes(5)->toIso8601String(),
            ],
        ]);
});

it('breaks a tie in failures by the latest one', function () {
    failedJob(now()->subHours(3)->toDateTimeString(), payload: json_encode(['displayName' => 'App\\Jobs\\Older']));
    failedJob(now()->subHours(2)->toDateTimeString(), payload: json_encode(['displayName' => 'App\\Jobs\\Newer']));

    expect(array_map(fn ($c) => $c->name, app(FailedJobsQuery::class)->byClass()->classes))->toBe(['App\\Jobs\\Newer', 'App\\Jobs\\Older']);
});

it('puts every unreadable payload in one group instead of failing', function (string $payload) {
    failedJob(now()->subMinute()->toDateTimeString(), payload: $payload);
    failedJob(now()->subMinutes(2)->toDateTimeString(), payload: 'not json');

    $classes = app(FailedJobsQuery::class)->byClass()->classes;

    expect($classes)->toHaveCount(1)
        ->and($classes[0]->name)->toBeNull()
        ->and($classes[0]->count)->toBe(2);
})->with(['array' => '[1, 2]', 'no name' => '{"job": "x"}', 'blank name' => '{"displayName": "  "}']);

it('flags the hour over the alert line and counts a failure exactly an hour old', function () {
    $alert = (int) config('platform.admin.failed_jobs_alert_per_hour');

    foreach (range(1, $alert) as $_) {
        failedJob(now()->subMinutes(30)->toDateTimeString());
    }
    failedJob(now()->subHour()->subSecond()->toDateTimeString());

    expect(app(FailedJobsQuery::class)->byClass())->lastHour->toBe($alert)->overThreshold->toBeFalse();

    failedJob(now()->subHour()->toDateTimeString());

    expect(app(FailedJobsQuery::class)->byClass())->lastHour->toBe($alert + 1)->overThreshold->toBeTrue();
});

it('is empty with no failures', function () {
    expect(app(FailedJobsQuery::class)->byClass())->total->toBe(0)->classes->toBe([])->overThreshold->toBeFalse();
});
