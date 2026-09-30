<?php

namespace App\Console\Commands\Media;

use App\Domain\Media\Services\MediaLifecycleService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class PurgeDeletedCommand extends Command
{
    protected $signature = 'media:purge-deleted {--dry-run : Count what would be purged without deleting it}';

    protected $description = 'Hard-delete media soft-deleted longer ago than the recovery window';

    public function handle(MediaLifecycleService $lifecycle): int
    {
        $started = microtime(true);
        $dryRun = (bool) $this->option('dry-run');

        $purged = $lifecycle->purgeDeleted($dryRun);

        Log::info('media.purged', [
            'purged' => $purged,
            'dry_run' => $dryRun,
            'duration_ms' => (int) round((microtime(true) - $started) * 1000),
        ]);
        $this->components->info(($dryRun ? 'Would purge' : 'Purging')." {$purged} deleted media.");

        return self::SUCCESS;
    }
}
