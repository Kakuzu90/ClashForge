<?php

namespace App\Console\Commands\Platform;

use App\Domain\Auth\Services\AccountDeletionService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class AnonymizeDeletedCommand extends Command
{
    protected $signature = 'platform:anonymize-deleted {--dry-run : Count due accounts without changing data}';

    protected $description = 'Anonymise accounts whose deletion grace period has ended';

    public function handle(AccountDeletionService $deletion): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $count = $deletion->anonymiseDue($dryRun);
        $this->components->info(($dryRun ? 'Would anonymise' : 'Anonymised')." {$count} accounts.");
        Log::info('auth.anonymise_deleted', ['count' => $count, 'dry_run' => $dryRun]);

        return self::SUCCESS;
    }
}
