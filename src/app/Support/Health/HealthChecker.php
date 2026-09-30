<?php

namespace App\Support\Health;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * NFR-OBS-4. Database and queue are checked live on every call; storage and the scheduler come
 * from state written by the scheduler, so an uptime ping never touches the bucket.
 */
class HealthChecker
{
    public const STORAGE_KEY = 'platform:health:storage';

    public const HEARTBEAT_KEY = 'platform:health:heartbeat';

    /**
     * @return array<string, HealthStatus>
     */
    public function checks(): array
    {
        return [
            'database' => $this->database(),
            'queue' => $this->queue(),
            'storage' => $this->storage(),
            'scheduler' => $this->scheduler(),
        ];
    }

    /**
     * @param  array<string, HealthStatus>  $checks
     */
    public function overall(array $checks): HealthStatus
    {
        $required = (array) config('platform.health.required');

        foreach ($checks as $name => $status) {
            if ($status === HealthStatus::Down && in_array($name, $required, true)) {
                return HealthStatus::Down;
            }
        }

        foreach ($checks as $status) {
            if ($status === HealthStatus::Down || $status === HealthStatus::Degraded) {
                return HealthStatus::Degraded;
            }
        }

        return HealthStatus::Ok;
    }

    /**
     * Probes the media bucket and records the result for the endpoint (`platform:check-health`).
     * One HEAD on a key that need not exist proves reachability and credentials without writing.
     */
    public function probeStorage(): HealthStatus
    {
        try {
            Storage::disk((string) config('media.disk'))->fileExists((string) config('platform.health.storage_probe_key'));
            $status = HealthStatus::Ok;
        } catch (Throwable $e) {
            Log::error('health.storage_down', ['error' => $e->getMessage()]);
            $status = HealthStatus::Down;
        }

        Cache::put(self::STORAGE_KEY, $status->value, (int) config('platform.health.external_ttl'));

        return $status;
    }

    public function beat(): void
    {
        Cache::put(self::HEARTBEAT_KEY, Date::now()->getTimestamp(), (int) config('platform.health.heartbeat_ttl'));
    }

    private function database(): HealthStatus
    {
        try {
            DB::select('select 1');

            return HealthStatus::Ok;
        } catch (Throwable) {
            return HealthStatus::Down;
        }
    }

    /**
     * Down when the jobs table cannot be read; degraded when a watched queue's oldest pending job
     * is older than its threshold (specs/20 §6).
     */
    private function queue(): HealthStatus
    {
        try {
            /** @var array<string, int> $thresholds */
            $thresholds = config('platform.health.queue_max_wait');
            $table = (string) config('queue.connections.database.table');
            $now = Date::now()->getTimestamp();

            foreach ($thresholds as $queue => $seconds) {
                $oldest = DB::table($table)->where('queue', $queue)->whereNull('reserved_at')->min('available_at');

                if ($oldest !== null && $oldest <= $now && $now - (int) $oldest > $seconds) {
                    return HealthStatus::Degraded;
                }
            }

            return HealthStatus::Ok;
        } catch (Throwable) {
            return HealthStatus::Down;
        }
    }

    private function storage(): HealthStatus
    {
        $cached = $this->cached(self::STORAGE_KEY);

        return is_string($cached) ? (HealthStatus::tryFrom($cached) ?? HealthStatus::Unknown) : HealthStatus::Unknown;
    }

    private function scheduler(): HealthStatus
    {
        $beat = $this->cached(self::HEARTBEAT_KEY);

        if (! is_int($beat)) {
            return HealthStatus::Unknown;
        }

        return Date::now()->getTimestamp() - $beat > (int) config('platform.health.heartbeat_max_age')
            ? HealthStatus::Down
            : HealthStatus::Ok;
    }

    /**
     * The cache lives in the database; if that is down the database check already says so.
     */
    private function cached(string $key): mixed
    {
        try {
            return Cache::get($key);
        } catch (Throwable) {
            return null;
        }
    }
}
