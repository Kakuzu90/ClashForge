<?php

namespace App\Console\Commands\Coc;

use App\Domain\PlayerAccounts\Services\SnapshotCompaction;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Thins out old CoC account snapshots (specs/07 retention, P2-21). Daily at 02:45; safe to run
 * twice. `--dry-run` counts what would go and deletes nothing.
 */
class CompactSnapshotsCommand extends Command
{
    protected $signature = 'coc:compact-snapshots {--dry-run : Count the snapshots that would be deleted, delete nothing}';

    protected $description = 'Keep one CoC account snapshot per day after 90 days and one per week after a year';

    public function handle(SnapshotCompaction $compaction): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $result = $compaction->run($dryRun);
        $verb = $dryRun ? 'Would delete' : 'Deleted';

        $this->components->info("{$verb} {$result['deleted']} snapshots across {$result['accounts']} accounts.");
        Log::info('coc.compact_snapshots', [...$result, 'dry_run' => $dryRun]);

        return self::SUCCESS;
    }
}
