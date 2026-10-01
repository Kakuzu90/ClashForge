<?php

namespace App\Console\Commands\Moderation;

use App\Domain\Moderation\Services\SanctionService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Ends suspensions past their end date and tells the account holder (specs/20 §2–3, every
 * 15 minutes). Safe to run twice.
 */
class ExpireSanctionsCommand extends Command
{
    protected $signature = 'moderation:expire-sanctions';

    protected $description = 'Lift suspensions past their end date and notify the account holders';

    public function handle(SanctionService $sanctions): int
    {
        $count = $sanctions->expireDue();

        $this->components->info($count === 1 ? '1 sanction ended.' : "{$count} sanctions ended.");
        Log::info('moderation.expire_sanctions', ['ended' => $count]);

        return self::SUCCESS;
    }
}
