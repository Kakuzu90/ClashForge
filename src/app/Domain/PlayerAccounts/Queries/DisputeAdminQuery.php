<?php

namespace App\Domain\PlayerAccounts\Queries;

use App\Domain\PlayerAccounts\Data\DisputeQueueData;
use App\Domain\PlayerAccounts\Data\DisputeQueueRowData;
use App\Domain\PlayerAccounts\Data\PendingDisputesData;
use App\Domain\PlayerAccounts\Enums\DisputeQueueView;
use App\Domain\PlayerAccounts\Enums\DisputeStatus;
use App\Domain\PlayerAccounts\Models\CocAccountDispute;
use App\Domain\PlayerAccounts\Support\DisputeRank;
use App\Domain\PlayerAccounts\Support\DisputeStake;
use App\Models\User;
use App\Support\Pagination\CursorShape;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Date;

/**
 * The admin dispute queue and the dashboard's pending disputes panel (FR-ADMIN-2, FR-ADMIN-5,
 * P2-17). Callers hold `resolve-disputes`. A dispute over a tag the admin has a stake in never shows
 * (owner decisions 2026-10-05 and 2026-10-06). Running disputes come oldest wait first; closed ones newest first.
 */
class DisputeAdminQuery
{
    /**
     * A cursor this view's ordering can read: the paginator's own shape, an integer id and a
     * timestamp in the view's sort column. Anything else is refused before it reaches the query.
     */
    public static function acceptsCursor(string $cursor, DisputeQueueView $view): bool
    {
        $parameters = CursorShape::parameters($cursor);
        $at = $parameters[self::sortColumn($view)] ?? null;

        return $parameters !== null && is_int($parameters['id'] ?? null)
            && is_string($at) && preg_match('/^\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}:\d{2}/', $at) === 1;
    }

    private static function sortColumn(DisputeQueueView $view): string
    {
        return $view === DisputeQueueView::Closed ? 'decided_at' : 'awaiting_since';
    }

    public function queue(User $admin, DisputeQueueView $view, bool $mine, ?string $cursor): DisputeQueueData
    {
        $query = $this->notAParty($admin)
            ->with(['claimant:id,username,role', 'holder:id,username,role'])
            ->whereIn('status', $view->statuses())
            ->when($mine, fn ($q) => $q->where('assigned_admin_id', $admin->id));

        $query = $view === DisputeQueueView::Closed
            ? $query->orderByDesc('decided_at')->orderByDesc('id')
            : $query->orderBy('awaiting_since')->orderBy('id');

        $page = $query->cursorPaginate((int) config('coc.disputes.queue_per_page'), ['*'], 'cursor', $cursor);
        $assigned = User::query()->withTrashed()->whereKey(collect($page->items())->pluck('assigned_admin_id')->filter()->unique()->all())->pluck('username', 'id');

        return new DisputeQueueData(
            entries: array_values(array_map(fn (CocAccountDispute $dispute): DisputeQueueRowData => new DisputeQueueRowData(
                ulid: $dispute->ulid,
                tag: '#'.$dispute->tag_normalized,
                claimant: $dispute->claimant->username ?? '',
                holder: $dispute->holder?->username,
                status: $dispute->status,
                statusLabel: $dispute->status->label(),
                waitingSince: $dispute->awaiting_since->toIso8601String(),
                openedAt: $dispute->created_at->toIso8601String(),
                assignedTo: $dispute->assigned_admin_id === null ? null : ($assigned[$dispute->assigned_admin_id] ?? null),
                blockedReason: $dispute->status->isActive() ? DisputeRank::blockedReason($admin, $dispute) : null,
            ), $page->items())),
            nextCursor: $page->nextCursor()?->encode(),
            previousCursor: $page->previousCursor()?->encode(),
        );
    }

    /**
     * The panel counts what the queue shows this admin, so a dispute they have a stake in is left out.
     * "Past the holder's window" is what the hourly sweep sends to the admins on its next run.
     */
    public function pending(User $admin): PendingDisputesData
    {
        $awaiting = $this->notAParty($admin)->where('status', DisputeStatus::AwaitingAdmin);
        $oldest = (clone $awaiting)->min('awaiting_since');

        return new PendingDisputesData(
            awaitingAdmin: $awaiting->count(),
            oldestWaitingSince: $oldest === null ? null : Date::parse((string) $oldest)->toIso8601String(),
            pastHolderWindow: $this->notAParty($admin)->whereIn('status', [DisputeStatus::Open, DisputeStatus::AwaitingHolder])
                ->where('awaiting_since', '<=', Date::now()->subDays((int) config('coc.disputes.holder_response_days')))->count(),
            running: $this->notAParty($admin)->active()->count(),
        );
    }

    /**
     * Disputes over a tag this admin has no stake in (DisputeStake): the queue and the counts show
     * what the review page would open for them.
     *
     * @return Builder<CocAccountDispute>
     */
    private function notAParty(User $admin): Builder
    {
        return CocAccountDispute::query()->whereNotIn('tag_normalized', DisputeStake::tags($admin));
    }
}
