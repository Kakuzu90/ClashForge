<?php

namespace App\Console\Commands\Coc;

use App\Domain\PlayerAccounts\Services\AccountSyncScheduler;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SyncAccountsCommand extends Command
{
    protected $signature = 'coc:sync-accounts';

    protected $description = 'Queue a sync for every CoC account that is due, within the background rate budget (specs/09 §6)';

    public function handle(AccountSyncScheduler $scheduler): int
    {
        $dispatched = $scheduler->dispatchDue();

        Log::info('coc.sync_dispatched', ['accounts' => $dispatched]);
        $this->components->info("Queued {$dispatched} account syncs.");

        return self::SUCCESS;
    }
}
