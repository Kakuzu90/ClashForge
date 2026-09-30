<?php

namespace App\Console\Commands\Media;

use App\Domain\Media\Services\MediaLifecycleService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SweepOrphansCommand extends Command
{
    protected $signature = 'media:sweep-orphans {--dry-run : Count what would be deleted without deleting it}';

    protected $description = 'Delete unattached media past its expiry, and re-queue deletions that stalled';

    public function handle(MediaLifecycleService $lifecycle): int
    {
        $started = microtime(true);
        $dryRun = (bool) $this->option('dry-run');

        $swept = $lifecycle->sweepOrphans($dryRun);
        $requeued = $lifecycle->requeueStalledDeletions($dryRun);

        Log::info('media.swept', [
            'swept' => $swept,
            'requeued' => $requeued,
            'dry_run' => $dryRun,
            'duration_ms' => (int) round((microtime(true) - $started) * 1000),
        ]);
        $this->components->info(($dryRun ? 'Would sweep' : 'Swept')." {$swept} orphaned media; {$requeued} stalled deletions re-queued.");

        return self::SUCCESS;
    }
}
