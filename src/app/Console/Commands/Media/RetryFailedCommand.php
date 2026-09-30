<?php

namespace App\Console\Commands\Media;

use App\Domain\Media\Services\MediaLifecycleService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class RetryFailedCommand extends Command
{
    protected $signature = 'media:retry-failed';

    protected $description = 'Re-queue recent media that failed with a processing error';

    public function handle(MediaLifecycleService $lifecycle): int
    {
        $started = microtime(true);
        $retried = $lifecycle->retryFailed();

        Log::info('media.retried', [
            'retried' => $retried,
            'duration_ms' => (int) round((microtime(true) - $started) * 1000),
        ]);
        $this->components->info("Re-queued {$retried} failed media.");

        return self::SUCCESS;
    }
}
