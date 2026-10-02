<?php

namespace App\Domain\Operations\Queries;

use App\Domain\Operations\Data\QueueStatData;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

/**
 * Depth and wait per queue from Laravel's `jobs` table (specs/20 §6). A job is waiting once its
 * `available_at` has passed and no worker holds it; a released or delayed job is not waiting yet,
 * and a reserved one is running (or returns after `retry_after`, specs/23 §9).
 */
class QueueStatsQuery
{
    /**
     * The specs/20 §1 queues first, in order, then any other queue name found in the table.
     *
     * @return list<QueueStatData>
     */
    public function stats(): array
    {
        $now = Date::now()->getTimestamp();
        /** @var list<string> $known */
        $known = config('platform.health.queues');
        /** @var array<string, int> $maxWait */
        $maxWait = config('platform.health.queue_max_wait');
        $maxDepth = (int) config('platform.health.queue_max_depth');

        $rows = DB::connection(config('queue.connections.database.connection'))
            ->table((string) config('queue.connections.database.table'))
            ->select('queue')
            ->selectRaw('SUM(CASE WHEN reserved_at IS NULL AND available_at <= ? THEN 1 ELSE 0 END) AS waiting', [$now])
            ->selectRaw('SUM(CASE WHEN reserved_at IS NULL AND available_at > ? THEN 1 ELSE 0 END) AS delayed', [$now])
            ->selectRaw('SUM(CASE WHEN reserved_at IS NOT NULL THEN 1 ELSE 0 END) AS reserved')
            ->selectRaw('MIN(CASE WHEN reserved_at IS NULL AND available_at <= ? THEN available_at END) AS oldest_available_at', [$now])
            ->groupBy('queue')
            ->get()
            ->keyBy('queue');

        $names = array_values(array_unique([...$known, ...$rows->keys()->map(fn (mixed $q): string => (string) $q)->sort()->all()]));

        return array_map(function (string $queue) use ($rows, $now, $maxWait, $maxDepth): QueueStatData {
            $row = $rows->get($queue);
            $waiting = (int) ($row->waiting ?? 0);
            $oldest = $row?->oldest_available_at === null ? null : max(0, $now - (int) $row->oldest_available_at);
            $limit = $maxWait[$queue] ?? null;

            return new QueueStatData(
                name: $queue,
                waiting: $waiting,
                delayed: (int) ($row->delayed ?? 0),
                reserved: (int) ($row->reserved ?? 0),
                oldestWaitSeconds: $oldest,
                maxWaitSeconds: $limit,
                maxDepth: $maxDepth,
                overWait: $limit !== null && $oldest !== null && $oldest > $limit,
                overDepth: $waiting > $maxDepth,
            );
        }, $names);
    }
}
