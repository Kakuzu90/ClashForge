<?php

namespace App\Console\Commands\Media;

use App\Domain\Media\Services\StorageReconciler;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ReconcileStorageCommand extends Command
{
    protected $signature = 'media:reconcile-storage {--dry-run : Report orphans without recording or deleting them}';

    protected $description = 'Diff media storage (public/, quarantine/, private/ only) against the database';

    public function handle(StorageReconciler $reconciler): int
    {
        $started = microtime(true);
        $dryRun = (bool) $this->option('dry-run');

        $report = $reconciler->reconcile($dryRun);

        Log::info('media.reconciled', [
            ...$report->toArray(),
            'dry_run' => $dryRun,
            'duration_ms' => (int) round((microtime(true) - $started) * 1000),
        ]);

        foreach ($report->toArray() as $label => $count) {
            $this->components->twoColumnDetail($label, (string) $count);
        }

        if ($report->missing > 0) {
            // Rows whose objects are gone: someone deleted from the bucket, or a write was lost.
            Log::error('media.reconcile.objects_missing', ['missing' => $report->missing]);

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
