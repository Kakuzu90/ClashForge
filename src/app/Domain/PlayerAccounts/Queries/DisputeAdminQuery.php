<?php

namespace App\Domain\PlayerAccounts\Queries;

use App\Domain\PlayerAccounts\Data\DisputeQueueData;
use App\Domain\PlayerAccounts\Data\DisputeQueueRowData;
use App\Domain\PlayerAccounts\Data\PendingDisputesData;
use App\Domain\PlayerAccounts\Enums\DisputeQueueView;
use App\Domain\PlayerAccounts\Enums\DisputeStatus;
use App\Domain\PlayerAccounts\Models\CocAccountDispute;
use App\Models\User;
use Illuminate\Support\Facades\Date;

/**
 * The admin dispute queue and the dashboard's pending disputes panel (FR-ADMIN-2, FR-ADMIN-5,
 * P2-17). Callers hold `resolve-disputes`. A dispute the admin is a party to never shows (owner
 * decision 2026-10-05). Running disputes come oldest wait first; closed ones newest first.
 */
class DisputeAdminQuery
{
    public function queue(User $admin, DisputeQueueView $view, bool $mine, ?string $cursor): DisputeQueueData
    {
        $query = CocAccountDispute::query()
            ->with(['claimant:id,username,role', 'holder:id,username,role'])
            ->whereIn('status', $view->statuses())
            ->where('claimant_id', '!=', $admin->id)
            ->where(fn ($q) => $q->whereNull('current_holder_id')->orWhere('current_holder_id', '!=', $admin->id))
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
                needsSuperAdmin: ! collect([$dispute->claimant, $dispute->holder])->filter()->every(fn (User $party): bool => $admin->role->outranks($party->role)),
            ), $page->items())),
            nextCursor: $page->nextCursor()?->encode(),
            previousCursor: $page->previousCursor()?->encode(),
        );
    }

    public function pending(): PendingDisputesData
    {
        $awaiting = CocAccountDispute::query()->where('status', DisputeStatus::AwaitingAdmin);
        $oldest = (clone $awaiting)->min('awaiting_since');

        return new PendingDisputesData(
            awaitingAdmin: $awaiting->count(),
            oldestWaitingSince: $oldest === null ? null : Date::parse((string) $oldest)->toIso8601String(),
            pastHolderWindow: CocAccountDispute::query()->where('status', DisputeStatus::Open)
                ->where('awaiting_since', '<=', Date::now()->subDays((int) config('coc.disputes.holder_response_days')))->count(),
            running: CocAccountDispute::query()->active()->count(),
        );
    }
}
