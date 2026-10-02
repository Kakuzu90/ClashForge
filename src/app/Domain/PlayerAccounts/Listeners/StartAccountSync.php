<?php

namespace App\Domain\PlayerAccounts\Listeners;

use App\Domain\PlayerAccounts\Events\CocAccountVerified;
use App\Domain\PlayerAccounts\Services\AccountSyncService;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * A verified account starts syncing (specs/09 §6), with the verification's data as its first
 * snapshot. Re-verifying restarts the schedule and clears any stale count.
 */
class StartAccountSync implements ShouldQueue
{
    public string $queue = 'default';

    public function __construct(private readonly AccountSyncService $sync) {}

    public function handle(CocAccountVerified $event): void
    {
        $this->sync->startAfterVerification($event->accountId);
    }
}
