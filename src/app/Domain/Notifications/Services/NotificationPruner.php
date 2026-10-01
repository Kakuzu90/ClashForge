<?php

namespace App\Domain\Notifications\Services;

use App\Domain\Notifications\Models\Notification;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Date;

/**
 * Retention (specs/16 §7): read notifications go after 90 days, unread ones after 180, and an
 * account keeps at most 500 rows, losing its oldest read ones first. Deletes in chunks so one
 * run never holds a long lock. Unread counts catch up within their cache TTL. A dry run counts
 * the same rows and deletes none.
 */
class NotificationPruner
{
    private const CHUNK = 1000;

    /**
     * @return array{read: int, unread: int, over_cap: int}
     */
    public function prune(bool $dryRun = false): array
    {
        $now = CarbonImmutable::instance(Date::now());
        $readBefore = $now->subDays((int) config('platform.notifications.prune_read_days'));
        $unreadBefore = $now->subDays((int) config('platform.notifications.prune_unread_days'));

        return [
            'read' => $this->deleteInChunks(Notification::query()->whereNotNull('read_at')->where('created_at', '<', $readBefore), $dryRun),
            'unread' => $this->deleteInChunks(Notification::query()->whereNull('read_at')->where('created_at', '<', $unreadBefore), $dryRun),
            'over_cap' => $this->trimToCap((int) config('platform.notifications.max_per_user'), $readBefore, $unreadBefore, $dryRun),
        ];
    }

    /**
     * Counts only rows the age rules keep, so a dry run reports what the real run removes.
     */
    private function trimToCap(int $cap, CarbonImmutable $readBefore, CarbonImmutable $unreadBefore, bool $dryRun): int
    {
        $kept = fn (Builder $query) => $query->where(fn (Builder $q) => $q
            ->where(fn (Builder $read) => $read->whereNotNull('read_at')->where('created_at', '>=', $readBefore))
            ->orWhere(fn (Builder $unread) => $unread->whereNull('read_at')->where('created_at', '>=', $unreadBefore)));

        $deleted = 0;

        $over = Notification::query()
            ->tap($kept)
            ->select(['notifiable_type', 'notifiable_id'])
            ->selectRaw('COUNT(*) AS total')
            ->groupBy('notifiable_type', 'notifiable_id')
            ->havingRaw('COUNT(*) > ?', [$cap])
            ->toBase()
            ->get();

        foreach ($over as $owner) {
            $ids = Notification::query()
                ->tap($kept)
                ->where('notifiable_type', $owner->notifiable_type)
                ->where('notifiable_id', $owner->notifiable_id)
                ->whereNotNull('read_at')
                ->orderBy('created_at')
                ->orderBy('id')
                ->limit((int) $owner->total - $cap)
                ->pluck('id');

            if ($dryRun) {
                $deleted += $ids->count();

                continue;
            }

            foreach ($ids->chunk(self::CHUNK) as $chunk) {
                $deleted += Notification::query()->whereKey($chunk->all())->delete();
            }
        }

        return $deleted;
    }

    /**
     * @param  Builder<Notification>  $query
     */
    private function deleteInChunks(Builder $query, bool $dryRun): int
    {
        if ($dryRun) {
            return $query->count();
        }

        $deleted = 0;

        do {
            $ids = (clone $query)->limit(self::CHUNK)->pluck('id');
            $deleted += $ids->isEmpty() ? 0 : Notification::query()->whereKey($ids->all())->delete();
        } while ($ids->count() === self::CHUNK);

        return $deleted;
    }
}
