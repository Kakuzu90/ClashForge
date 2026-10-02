<?php

namespace App\Support\Maintenance;

use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

/**
 * The framework-owned tables of specs/20 §2's nightly prune: failed jobs past
 * `platform.prune.failed_jobs_days`, expired cache entries and locks, and sessions past the idle
 * lifetime or the 30-day absolute cap (specs/04 §4).
 */
class OperationalTablePruner
{
    /**
     * @return array{failed_jobs: int, cache: int, cache_locks: int, sessions: int}
     */
    public function prune(bool $dryRun = false): array
    {
        $now = Date::now();
        $nowTs = $now->getTimestamp();
        // These keys exist with null values in the stock config files, so a default argument
        // would not apply.
        $cacheTable = config('cache.stores.database.table') ?: 'cache';
        $lockTable = config('cache.stores.database.lock_table') ?: 'cache_locks';

        $queries = [
            'failed_jobs' => DB::table(config('queue.failed.table') ?: 'failed_jobs')
                ->where('failed_at', '<', $now->subDays((int) config('platform.prune.failed_jobs_days'))),
            'cache' => DB::table($cacheTable)->where('expiration', '<', $nowTs),
            'cache_locks' => DB::table($lockTable)->where('expiration', '<', $nowTs),
            'sessions' => DB::table(config('session.table') ?: 'sessions')
                ->where(fn ($q) => $q
                    ->where('last_activity', '<', $now->subMinutes((int) config('session.lifetime'))->getTimestamp())
                    ->orWhere('created_at', '<', $now->subDays((int) config('platform.auth.absolute_session_days')))),
        ];

        return array_map(fn ($query): int => $dryRun ? $query->count() : $query->delete(), $queries);
    }
}
