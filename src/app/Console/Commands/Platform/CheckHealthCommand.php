<?php

namespace App\Console\Commands\Platform;

use App\Support\Health\HealthChecker;
use App\Support\Health\HealthStatus;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CheckHealthCommand extends Command
{
    protected $signature = 'platform:check-health';

    protected $description = 'Probe external dependencies (object storage) and record the result for /health';

    public function handle(HealthChecker $health): int
    {
        $storage = $health->probeStorage();
        $this->components->twoColumnDetail('storage', $storage->value);
        Log::info('platform.check_health', ['storage' => $storage->value]);

        return $storage === HealthStatus::Ok ? self::SUCCESS : self::FAILURE;
    }
}
