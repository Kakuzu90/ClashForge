<?php

namespace App\Domain\CocIntegration\Services;

use App\Domain\CocIntegration\Data\CocApiHealthData;
use App\Domain\CocIntegration\Data\CocKeyData;
use App\Domain\CocIntegration\Data\CocKeyStatusData;
use App\Domain\CocIntegration\Enums\SyncResourceType;
use App\Domain\CocIntegration\Models\CocApiRequest;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Date;

/**
 * Reads for the admin dashboard's API panel (FR-ADMIN-5, specs/18 §6) and the System Health page.
 * It never calls the API: the breaker and the keys come from the cache, the rest from
 * `coc_api_requests`, and the sync success rate from `sync_states`.
 */
class CocApiHealthReport
{
    public function __construct(
        private readonly CocApiStatus $status,
        private readonly CocKeyPool $keys,
        private readonly SyncSchedule $sync,
    ) {}

    public function summary(): CocApiHealthData
    {
        $hours = (int) config('coc.health.window_hours');
        $since = Date::now()->subHours($hours);
        $window = fn (): Builder => CocApiRequest::query()->where('created_at', '>=', $since);

        $counts = $window()
            ->selectRaw('COUNT(*) FILTER (WHERE NOT was_cached) AS calls')
            ->selectRaw('COUNT(*) FILTER (WHERE was_cached) AS cache_hits')
            ->toBase()->first();
        $failures = $this->failures($window())->count();
        $topError = $this->failures($window())
            ->whereNotNull('error_code')
            ->groupBy('error_code')->orderByRaw('COUNT(*) DESC')->orderBy('error_code')
            ->value('error_code');

        $calls = (int) ($counts->calls ?? 0);
        $state = $this->status->state();
        $pool = $this->keys->status();
        $rate = $this->sync->rate(SyncResourceType::CocAccount);
        $alert = (float) config('coc.sync.success_alert');

        return new CocApiHealthData(
            state: $state->state,
            reason: $state->reason,
            openUntil: $state->openUntil,
            keysHealthy: $pool->healthy,
            keysTotal: $pool->total,
            windowHours: $hours,
            calls: $calls,
            cacheHits: (int) ($counts->cache_hits ?? 0),
            failures: $failures,
            failureRate: $calls === 0 ? null : round($failures / $calls, 3),
            topError: is_string($topError) ? $topError : null,
            syncWindowMinutes: $rate->windowMinutes,
            syncAttempts: $rate->attempts,
            syncSuccesses: $rate->successes,
            syncSuccessRate: $rate->rate,
            syncAlert: $alert,
            syncBelowAlert: $rate->rate !== null && $rate->rate < $alert,
            syncStopped: $this->sync->stoppedCount(SyncResourceType::CocAccount),
        );
    }

    /**
     * Every key of the pool with its state, for the System Health page.
     *
     * @return list<CocKeyData>
     */
    public function keys(): array
    {
        return array_map(fn (CocKeyStatusData $key): CocKeyData => new CocKeyData(
            id: $key->id,
            healthy: $key->healthy,
            reason: $key->reason,
            unhealthySince: $key->unhealthySince,
        ), $this->keys->status()->keys);
    }

    /**
     * @param  Builder<CocApiRequest>  $query
     * @return Builder<CocApiRequest>
     */
    private function failures(Builder $query): Builder
    {
        return $query->where('was_cached', false)
            ->where(fn (Builder $q) => $q->whereNull('status_code')->orWhere('status_code', '>=', 500));
    }
}
