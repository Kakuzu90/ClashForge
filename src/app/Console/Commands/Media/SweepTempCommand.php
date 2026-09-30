<?php

namespace App\Console\Commands\Media;

use App\Domain\Media\Services\MediaLifecycleService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SweepTempCommand extends Command
{
    protected $signature = 'media:sweep-temp {--dry-run : Count what would be removed without removing it}';

    protected $description = 'Remove media temp directories left by a killed worker (run before the media worker starts)';

    public function handle(MediaLifecycleService $lifecycle): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $removed = $lifecycle->sweepTempDirs($dryRun);

        Log::info('media.temp_swept', ['removed' => $removed, 'dry_run' => $dryRun]);
        $this->components->info(($dryRun ? 'Would remove' : 'Removed')." {$removed} stale temp directories.");

        return self::SUCCESS;
    }
}
