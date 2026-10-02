<?php

namespace App\Domain\Operations\Queries;

use App\Domain\Operations\Data\FailedJobClassData;
use App\Domain\Operations\Data\FailedJobGroupData;
use App\Domain\Operations\Data\FailedJobsByClassData;
use App\Domain\Operations\Data\FailedJobsSummaryData;
use Carbon\CarbonImmutable;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Reads Laravel's `failed_jobs` table (specs/20 §5). The alert threshold is the specs/20 §6
 * "failed jobs per hour" line. Class names and queues only: payloads and exception text can hold
 * personal data and never leave the server.
 */
class FailedJobsQuery
{
    private const NAME_MAX = 200;

    public function summary(): FailedJobsSummaryData
    {
        $now = CarbonImmutable::instance(Date::now());
        $connection = DB::connection((string) config('queue.failed.database'));
        $table = (string) config('queue.failed.table');
        $alert = (int) config('platform.admin.failed_jobs_alert_per_hour');

        $lastHour = $connection->table($table)->where('failed_at', '>=', $now->subHour())->count();

        $classes = $connection->table($table)
            ->selectRaw(self::nameExpression($connection).' AS name, COUNT(*) AS failures, MAX(failed_at) AS last_failed_at')
            ->where('failed_at', '>=', $now->subDay())
            ->groupByRaw('1')
            ->orderByDesc('failures')
            ->orderByDesc('last_failed_at')
            ->get();

        $top = [];
        foreach ($classes->take((int) config('platform.admin.failed_jobs_top_classes')) as $row) {
            $top[] = new FailedJobClassData(
                name: self::name($row->name),
                count: (int) $row->failures,
                lastFailedAt: CarbonImmutable::parse($row->last_failed_at)->toIso8601String(),
            );
        }

        return new FailedJobsSummaryData(
            lastHour: $lastHour,
            last24Hours: (int) $classes->sum('failures'),
            alertPerHour: $alert,
            overThreshold: $lastHour > $alert,
            topClasses: $top,
        );
    }

    /**
     * Every failure still kept (`platform.prune.failed_jobs_days`), one row per job class with the
     * queues it failed on, for the System Health page (specs/20 §5–6).
     */
    public function byClass(): FailedJobsByClassData
    {
        $now = CarbonImmutable::instance(Date::now());
        $connection = DB::connection((string) config('queue.failed.database'));
        $alert = (int) config('platform.admin.failed_jobs_alert_per_hour');
        $hourAgo = $now->subHour();

        $rows = $connection->table((string) config('queue.failed.table'))
            ->selectRaw(self::nameExpression($connection).' AS name, queue, COUNT(*) AS failures, MIN(failed_at) AS first_failed_at, MAX(failed_at) AS last_failed_at')
            ->selectRaw('SUM(CASE WHEN failed_at >= ? THEN 1 ELSE 0 END) AS last_hour', [$hourAgo])
            ->groupByRaw('1, 2')
            ->get();

        $groups = [];
        foreach ($rows as $row) {
            $name = self::name($row->name);
            $group = $groups[$name ?? ''] ?? ['name' => $name, 'queues' => [], 'count' => 0, 'lastHour' => 0, 'first' => null, 'last' => null];
            $first = CarbonImmutable::parse($row->first_failed_at);
            $last = CarbonImmutable::parse($row->last_failed_at);

            $group['queues'][] = (string) $row->queue;
            $group['count'] += (int) $row->failures;
            $group['lastHour'] += (int) $row->last_hour;
            $group['first'] = $group['first'] === null || $first->lt($group['first']) ? $first : $group['first'];
            $group['last'] = $group['last'] === null || $last->gt($group['last']) ? $last : $group['last'];
            $groups[$name ?? ''] = $group;
        }

        $classes = array_map(function (array $group): FailedJobGroupData {
            $queues = array_values(array_unique($group['queues']));
            sort($queues);

            return new FailedJobGroupData(
                name: $group['name'],
                queues: $queues,
                count: $group['count'],
                lastHour: $group['lastHour'],
                firstFailedAt: $group['first']->toIso8601String(),
                lastFailedAt: $group['last']->toIso8601String(),
            );
        }, array_values($groups));

        usort($classes, fn (FailedJobGroupData $a, FailedJobGroupData $b): int => [$b->count, $b->lastFailedAt] <=> [$a->count, $a->lastFailedAt]);
        $lastHour = array_sum(array_map(fn (FailedJobGroupData $c): int => $c->lastHour, $classes));

        return new FailedJobsByClassData(
            total: array_sum(array_map(fn (FailedJobGroupData $c): int => $c->count, $classes)),
            lastHour: $lastHour,
            alertPerHour: $alert,
            overThreshold: $lastHour > $alert,
            retentionDays: (int) config('platform.prune.failed_jobs_days'),
            classes: $classes,
        );
    }

    /**
     * The job's `displayName` from its JSON payload, null for a payload that is not a JSON object,
     * so one malformed row cannot fail the query.
     */
    private static function nameExpression(Connection $connection): string
    {
        return $connection->getDriverName() === 'pgsql'
            ? "CASE WHEN payload IS JSON OBJECT THEN payload::json->>'displayName' END"
            : "CASE WHEN json_valid(payload) AND json_type(payload) = 'object' THEN json_extract(payload, '$.displayName') END";
    }

    public static function name(mixed $raw): ?string
    {
        if (! is_string($raw)) {
            return null;
        }

        $name = trim($raw);

        return $name === '' ? null : Str::limit($name, self::NAME_MAX);
    }
}
