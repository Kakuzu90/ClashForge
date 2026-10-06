<?php

namespace App\Domain\PlayerAccounts\Services;

use App\Domain\PlayerAccounts\Enums\SnapshotSource;
use App\Domain\PlayerAccounts\Models\CocAccountSnapshot;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

/**
 * `coc:compact-snapshots` (specs/07 `coc_account_snapshots` retention, specs/20 §3, P2-21). Every
 * snapshot younger than `coc.snapshots.keep_all_days` stays; older ones keep the last row of each
 * UTC day, and past `daily_until_days` the last row of each ISO week (Monday, UTC), the state the
 * account ended that period in. Verification snapshots always stay: they mark when ownership was
 * proven, and the dispute review reads them. An account's newest row is the last of its bucket, so
 * it always stays too, whatever its age (FR-COC-14).
 *
 * Accounts go `coc.snapshots.batch_size` at a time in id order, one delete per batch, so a run that
 * stops halfway is safe to repeat, and a second run deletes nothing.
 */
class SnapshotCompaction
{
    /**
     * @return array{accounts: int, deleted: int} accounts with old rows looked at, rows deleted (or
     *                                            that would be, on a dry run)
     */
    public function run(bool $dryRun = false): array
    {
        $now = CarbonImmutable::instance(Date::now());
        $keepAll = $now->subDays((int) config('coc.snapshots.keep_all_days'));
        $daily = $now->subDays((int) config('coc.snapshots.daily_until_days'));
        $batch = max(1, (int) config('coc.snapshots.batch_size'));
        $totals = ['accounts' => 0, 'deleted' => 0];
        $after = 0;

        do {
            $ids = CocAccountSnapshot::query()->where('captured_at', '<', $keepAll)->where('coc_account_id', '>', $after)
                ->distinct()->orderBy('coc_account_id')->limit($batch)->pluck('coc_account_id')
                ->map(fn (mixed $id): int => (int) $id)->all();

            if ($ids === []) {
                break;
            }

            $after = $ids[count($ids) - 1];
            $doomed = $this->doomed($ids, $keepAll, $daily);
            $totals['accounts'] += count($ids);
            $totals['deleted'] += $dryRun
                ? DB::query()->fromSub($doomed, 'doomed')->count()
                : CocAccountSnapshot::query()->whereIn('id', $doomed)->delete();
        } while (count($ids) === $batch);

        return $totals;
    }

    /**
     * The ids of these accounts' rows that are not the last of their bucket. A row younger than
     * `$keepAll` is a bucket of its own.
     *
     * @param  array<int, int>  $accountIds
     */
    private function doomed(array $accountIds, CarbonImmutable $keepAll, CarbonImmutable $daily): Builder
    {
        $bucket = $this->bucket();

        $ranked = DB::table('coc_account_snapshots')
            ->select(['id', 'source'])
            ->selectRaw("ROW_NUMBER() OVER (PARTITION BY coc_account_id, {$bucket} ORDER BY captured_at DESC, id DESC) AS place", [$keepAll, $daily])
            ->whereIn('coc_account_id', $accountIds);

        return DB::query()->fromSub($ranked, 'ranked')
            ->where('place', '>', 1)
            ->where('source', '!=', SnapshotSource::Verification->value)
            ->select('id');
    }

    /**
     * The bucket key: the row itself while recent, then its UTC day, then the Monday of its ISO week.
     */
    private function bucket(): string
    {
        if (DB::getDriverName() === 'pgsql') {
            return "CASE WHEN captured_at >= ? THEN 'r' || id"
                ." WHEN captured_at >= ? THEN 'd' || to_char(captured_at AT TIME ZONE 'UTC', 'YYYY-MM-DD')"
                ." ELSE 'w' || to_char(date_trunc('week', captured_at AT TIME ZONE 'UTC'), 'YYYY-MM-DD') END";
        }

        // SQLite stores UTC text; 'weekday 0' moves to the Sunday ending the week, six days back is its Monday.
        return "CASE WHEN captured_at >= ? THEN 'r' || id"
            ." WHEN captured_at >= ? THEN 'd' || date(captured_at)"
            ." ELSE 'w' || date(captured_at, 'weekday 0', '-6 days') END";
    }
}
