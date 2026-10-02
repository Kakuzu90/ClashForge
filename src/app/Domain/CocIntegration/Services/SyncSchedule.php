<?php

namespace App\Domain\CocIntegration\Services;

use App\Domain\CocIntegration\Data\SyncRateData;
use App\Domain\CocIntegration\Data\SyncStateData;
use App\Domain\CocIntegration\Enums\SyncResourceType;
use App\Domain\CocIntegration\Enums\SyncTier;
use App\Domain\CocIntegration\Models\SyncState;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

/**
 * When each synced resource is next due (specs/09 §6, specs/07 `sync_states`). The caller decides
 * the tier from its own data; this class owns the timing: tier intervals, exponential backoff
 * after a failure, and the frozen state that retries weekly and then stops.
 */
class SyncSchedule
{
    /**
     * Ids due now, oldest first.
     *
     * @return list<int>
     */
    public function due(SyncResourceType $type, int $limit): array
    {
        if ($limit < 1) {
            return [];
        }

        return array_values(SyncState::query()
            ->where('resource_type', $type)
            ->whereNotNull('next_due_at')
            ->where('next_due_at', '<=', Date::now())
            ->orderBy('next_due_at')->orderBy('id')
            ->limit($limit)
            ->pluck('resource_id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all());
    }

    /**
     * Takes the due rows for this run: each moves `$seconds` ahead before its job is queued, so a
     * job still waiting in a backlog is not queued again on the next tick (specs/20 §4 rule 1).
     * The job's outcome then sets the real next time; a job that never ran frees the row again.
     *
     * @return list<int>
     */
    public function claimDue(SyncResourceType $type, int $limit, int $seconds): array
    {
        return DB::transaction(function () use ($type, $limit, $seconds): array {
            $ids = $this->due($type, $limit);

            if ($ids !== []) {
                SyncState::query()->where('resource_type', $type)->whereIn('resource_id', $ids)
                    ->update(['next_due_at' => Date::now()->addSeconds($seconds)]);
            }

            return $ids;
        });
    }

    /**
     * (Re)starts the schedule after fresh data arrived some other way, e.g. a verification.
     */
    public function start(SyncResourceType $type, int $id, SyncTier $tier): void
    {
        $now = CarbonImmutable::instance(Date::now());

        SyncState::query()->updateOrCreate(
            ['resource_type' => $type, 'resource_id' => $id],
            [
                'last_success_at' => $now,
                'consecutive_failures' => 0,
                'frozen_attempts' => 0,
                'tier' => $tier,
                'next_due_at' => $now->addSeconds($tier->intervalSeconds()),
            ],
        );
    }

    public function recordSuccess(SyncResourceType $type, int $id, SyncTier $tier): SyncStateData
    {
        $now = CarbonImmutable::instance(Date::now());
        $state = $this->locked($type, $id, fn (SyncState $row): array => [
            'last_attempt_at' => $now,
            'last_success_at' => $now,
            'consecutive_failures' => 0,
            'frozen_attempts' => 0,
            'tier' => $tier,
            'next_due_at' => $now->addSeconds($tier->intervalSeconds()),
        ]);

        return self::data($state);
    }

    /**
     * A failed attempt: backs off exponentially, freezes after `coc.sync.frozen_after` failures in
     * a row (or at once when `$freeze`, e.g. a tag the API stopped finding), and stops a frozen
     * resource after `coc.sync.frozen_max_attempts` failed weekly retries.
     */
    public function recordFailure(SyncResourceType $type, int $id, bool $freeze = false): SyncStateData
    {
        $now = CarbonImmutable::instance(Date::now());
        $state = $this->locked($type, $id, function (SyncState $row) use ($now, $freeze): array {
            $failures = $row->consecutive_failures + 1;
            $frozenInterval = SyncTier::Frozen->intervalSeconds();

            if ($row->tier === SyncTier::Frozen && $row->exists) {
                $attempts = $row->frozen_attempts + 1;

                return [
                    'last_attempt_at' => $now,
                    'consecutive_failures' => $failures,
                    'frozen_attempts' => $attempts,
                    'next_due_at' => $attempts >= (int) config('coc.sync.frozen_max_attempts') ? null : $now->addSeconds($frozenInterval),
                ];
            }

            if ($freeze || $failures >= (int) config('coc.sync.frozen_after')) {
                return [
                    'last_attempt_at' => $now,
                    'consecutive_failures' => $failures,
                    'frozen_attempts' => 0,
                    'tier' => SyncTier::Frozen,
                    'next_due_at' => $now->addSeconds($frozenInterval),
                ];
            }

            $backoff = (int) config('coc.sync.backoff_base') * 2 ** ($failures - 1);

            return [
                'last_attempt_at' => $now,
                'consecutive_failures' => $failures,
                'next_due_at' => $now->addSeconds(min($backoff, $row->tier->intervalSeconds())),
            ];
        });

        return self::data($state);
    }

    /**
     * Not the resource's fault (the API is down, or our budget or keys ran out): try again later
     * without counting a failure.
     */
    public function postpone(SyncResourceType $type, int $id, int $seconds): void
    {
        SyncState::query()->where('resource_type', $type)->where('resource_id', $id)
            ->update(['next_due_at' => Date::now()->addSeconds(max(1, $seconds))]);
    }

    /**
     * No longer synced in the background (released, suspended, unverified): the row goes, so it
     * is never mistaken for one that stopped after failing. A later verification starts afresh.
     */
    public function stop(SyncResourceType $type, int $id): void
    {
        SyncState::query()->where('resource_type', $type)->where('resource_id', $id)->delete();
    }

    /**
     * Attempts in the last `coc.sync.success_window_minutes` and how many succeeded. Each row
     * holds its latest attempt only; at the shortest tier (hours) a row attempts at most once per
     * window outside backoff, so this is close to the true rate.
     */
    public function rate(SyncResourceType $type): SyncRateData
    {
        $minutes = (int) config('coc.sync.success_window_minutes');
        $row = SyncState::query()
            ->where('resource_type', $type)
            ->where('last_attempt_at', '>=', Date::now()->subMinutes($minutes))
            ->selectRaw('COUNT(*) AS attempts')
            ->selectRaw('SUM(CASE WHEN last_success_at >= last_attempt_at THEN 1 ELSE 0 END) AS successes')
            ->toBase()->first();

        $attempts = (int) ($row->attempts ?? 0);
        $successes = (int) ($row->successes ?? 0);

        return new SyncRateData($minutes, $attempts, $successes, $attempts === 0 ? null : round($successes / $attempts, 3));
    }

    /**
     * Frozen resources out of retries. Unsynced resources have no row (`stop()`), so they never
     * count here.
     */
    public function stoppedCount(SyncResourceType $type): int
    {
        return SyncState::query()->where('resource_type', $type)->where('tier', SyncTier::Frozen)->whereNull('next_due_at')->count();
    }

    /**
     * @param  callable(SyncState): array<string, mixed>  $changes
     */
    private function locked(SyncResourceType $type, int $id, callable $changes): SyncState
    {
        return DB::transaction(function () use ($type, $id, $changes): SyncState {
            $row = SyncState::query()->where('resource_type', $type)->where('resource_id', $id)->lockForUpdate()->first()
                ?? new SyncState(['resource_type' => $type, 'resource_id' => $id, 'tier' => SyncTier::Cold]);
            $row->fill($changes($row))->save();

            return $row;
        });
    }

    private static function data(SyncState $state): SyncStateData
    {
        return new SyncStateData($state->tier, $state->consecutive_failures, $state->next_due_at);
    }
}
