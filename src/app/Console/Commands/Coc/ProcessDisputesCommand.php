<?php

namespace App\Console\Commands\Coc;

use App\Domain\PlayerAccounts\Services\DisputeService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Sends disputes the holder did not answer in time to the admins, withdraws those left waiting on
 * the claimant (specs/13 §5), and reminds holders on day 3 and day 6 (specs/16 §2). Hourly; safe to
 * run twice.
 */
class ProcessDisputesCommand extends Command
{
    protected $signature = 'coc:process-disputes';

    protected $description = 'Escalate unanswered ownership disputes, withdraw abandoned ones and remind holders';

    public function handle(DisputeService $disputes): int
    {
        $result = $disputes->sweep();

        $this->components->info("{$result['escalated']} escalated, {$result['withdrawn']} withdrawn, {$result['reminded']} reminded.");
        Log::info('coc.process_disputes', $result);

        return self::SUCCESS;
    }
}
