<?php

namespace App\Domain\Bases\Listeners;

use App\Domain\Bases\Models\BaseLayout;
use App\Domain\PlayerAccounts\Events\CocAccountOwnershipTransferred;
use App\Domain\PlayerAccounts\Events\CocAccountReleased;
use App\Domain\PlayerAccounts\Services\AccountCredits;
use Illuminate\Events\Dispatcher;

/**
 * A base keeps its author but loses its credit once the author no longer holds the credited
 * account (specs/08 §3.2, specs/13 §6, specs/23 §3): a detach, a deletion or ban release, a
 * token supersede or a dispute transfer. Authorship is never evidence of tag ownership.
 */
class DropLostCredits
{
    public function __construct(private readonly AccountCredits $accounts) {}

    public function handleReleased(CocAccountReleased $event): void
    {
        $this->forAuthor($event->userId);
    }

    public function handleTransferred(CocAccountOwnershipTransferred $event): void
    {
        $this->forAuthor($event->fromUserId);
    }

    /**
     * @return array<class-string, string>
     */
    public function subscribe(Dispatcher $events): array
    {
        return [
            CocAccountReleased::class => 'handleReleased',
            CocAccountOwnershipTransferred::class => 'handleTransferred',
        ];
    }

    private function forAuthor(int $userId): void
    {
        $credited = BaseLayout::withTrashed()->where('user_id', $userId)->whereNotNull('coc_account_id')
            ->distinct()->pluck('coc_account_id')->map(fn (mixed $id): int => (int) $id)->values()->all();
        $lost = array_values(array_diff($credited, $this->accounts->heldIds($userId, $credited)));

        if ($lost !== []) {
            // Not an edit by the author, so `updated_at` stays (FR-BASE-15 counts edits by time).
            BaseLayout::withTrashed()->where('user_id', $userId)->whereIn('coc_account_id', $lost)->toBase()->update(['coc_account_id' => null]);
        }
    }
}
