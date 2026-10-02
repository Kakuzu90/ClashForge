<?php

namespace App\Domain\PlayerAccounts\Services;

use App\Domain\CocIntegration\Enums\SyncResourceType;
use App\Domain\CocIntegration\Services\CocApiStatus;
use App\Domain\CocIntegration\Services\SyncSchedule;
use App\Domain\PlayerAccounts\Jobs\SyncCocAccountJob;

/**
 * `coc:sync-accounts` (specs/09 §6): one job per due account, no more than the background budget
 * left this minute, so the scheduler throttles itself. Nothing goes out while the circuit is open.
 */
class AccountSyncScheduler
{
    public function __construct(
        private readonly SyncSchedule $schedule,
        private readonly CocApiStatus $status,
    ) {}

    public function dispatchDue(): int
    {
        if (! $this->status->isAvailable()) {
            return 0;
        }

        $limit = min((int) config('coc.sync.batch_size'), $this->status->backgroundBudgetRemaining());
        $ids = $this->schedule->claimDue(SyncResourceType::CocAccount, $limit, (int) config('coc.sync.claim_seconds'));

        foreach ($ids as $id) {
            SyncCocAccountJob::dispatch($id);
        }

        return count($ids);
    }
}
