<?php

namespace App\Console\Commands\Coc;

use App\Domain\PlayerAccounts\Services\DisputeService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Sends disputes the holder did not answer in time to the admins, and withdraws those left
 * waiting on the claimant (specs/13 §5). Hourly; safe to run twice.
 */
class ProcessDisputesCommand extends Command
{
    protected $signature = 'coc:process-disputes';

    protected $description = 'Escalate unanswered ownership disputes and withdraw abandoned ones';

    public function handle(DisputeService $disputes): int
    {
        $result = $disputes->sweep();

        $this->components->info("{$result['escalated']} escalated, {$result['withdrawn']} withdrawn.");
        Log::info('coc.process_disputes', $result);

        return self::SUCCESS;
    }
}
