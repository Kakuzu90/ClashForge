<?php

namespace App\Console\Commands\Auth;

use App\Domain\Auth\Services\UnverifiedAccountLifecycleService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ProcessUnverifiedAccountsCommand extends Command
{
    protected $signature = 'auth:process-unverified {--dry-run : Count actions without queuing email or changing accounts}';

    protected $description = 'Remind, warn and anonymise never-verified ordinary accounts';

    public function handle(UnverifiedAccountLifecycleService $lifecycle): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $counts = $lifecycle->process($dryRun);
        $this->components->info(($dryRun ? 'Would process' : 'Processed').": {$counts['reminders']} reminders, {$counts['warnings']} warnings, {$counts['purged']} purged accounts.");
        Log::info('auth.unverified_processed', [...$counts, 'dry_run' => $dryRun]);

        return self::SUCCESS;
    }
}
