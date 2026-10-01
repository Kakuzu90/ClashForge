<?php

namespace App\Support\Health;

use Carbon\CarbonImmutable;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Reads Laravel's `failed_jobs` table, which no module owns (specs/20 §5). The alert threshold is
 * the specs/20 §6 "failed jobs per hour" line.
 */
class FailedJobsSummary
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
