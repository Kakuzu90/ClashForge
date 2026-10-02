<?php

use App\Domain\Operations\Queries\QueueStatsQuery;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

// specs/20 §6: depth and oldest wait per queue, from Laravel's `jobs` table.

beforeEach(fn () => Date::setTestNow('2026-10-02 12:00:00'));

function queueJob(string $queue, int $availableAgo = 0, ?int $reservedAgo = null): void
{
    $now = Date::now()->getTimestamp();

    DB::table((string) config('queue.connections.database.table'))->insert([
        'queue' => $queue,
        'payload' => json_encode(['displayName' => 'App\\Jobs\\Example']),
        'attempts' => 0,
        'reserved_at' => $reservedAgo === null ? null : $now - $reservedAgo,
        'available_at' => $now - $availableAgo,
        'created_at' => $now - max($availableAgo, 0),
    ]);
}

function queueStat(string $name): array
{
    return collect(app(QueueStatsQuery::class)->stats())->firstOrFail(fn ($q) => $q->name === $name)->toArray();
}

it('lists the specs/20 §1 queues in order even when empty, then any other queue found', function () {
    queueJob('reports');

    expect(array_map(fn ($q) => $q->name, app(QueueStatsQuery::class)->stats()))
        ->toBe([...config('platform.health.queues'), 'reports'])
        ->and(queueStat('high'))->toMatchArray(['waiting' => 0, 'delayed' => 0, 'reserved' => 0, 'oldestWaitSeconds' => null, 'overWait' => false]);
});

it('tells waiting, delayed and running jobs apart, and ages only the waiting ones', function () {
    queueJob('default', availableAgo: 90);
    queueJob('default', availableAgo: 10);
    queueJob('default', availableAgo: -600);
    queueJob('default', availableAgo: 4000, reservedAgo: 30);

    expect(queueStat('default'))->toMatchArray([
        'waiting' => 2,
        'delayed' => 1,
        'reserved' => 1,
        'oldestWaitSeconds' => 90,
        'maxWaitSeconds' => config('platform.health.queue_max_wait.default'),
        'overWait' => false,
    ]);
});

it('flags a wait over the queue line, and leaves queues without one unflagged', function () {
    $line = (int) config('platform.health.queue_max_wait.high');
    queueJob('high', availableAgo: $line + 1);
    queueJob('media', availableAgo: 86400);

    expect(queueStat('high'))->toMatchArray(['oldestWaitSeconds' => $line + 1, 'overWait' => true])
        ->and(queueStat('media'))->toMatchArray(['maxWaitSeconds' => null, 'overWait' => false]);
});

it('does not flag a wait exactly on the line', function () {
    queueJob('high', availableAgo: (int) config('platform.health.queue_max_wait.high'));

    expect(queueStat('high')['overWait'])->toBeFalse();
});

it('flags a queue with more jobs waiting than the depth line', function () {
    config(['platform.health.queue_max_depth' => 2]);
    queueJob('low');
    queueJob('low');

    expect(queueStat('low')['overDepth'])->toBeFalse();

    queueJob('low');

    expect(queueStat('low'))->toMatchArray(['waiting' => 3, 'maxDepth' => 2, 'overDepth' => true]);
});
