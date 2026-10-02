<?php

namespace App\Console\Commands\Platform;

use App\Domain\CocIntegration\Services\CocHealthCheck;
use App\Support\Health\HealthChecker;
use App\Support\Health\HealthStatus;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CheckHealthCommand extends Command
{
    protected $signature = 'platform:check-health';

    protected $description = 'Probe external dependencies (object storage, CoC API keys) and record the result for /health';

    public function handle(HealthChecker $health, CocHealthCheck $coc): int
    {
        $storage = $health->probeStorage();
        $this->components->twoColumnDetail('storage', $storage->value);

        // A degraded key pool still serves; only a pool with no working key fails the run.
        $cocStatus = $coc->run();
        $this->components->twoColumnDetail('coc', $cocStatus->value);

        Log::info('platform.check_health', ['storage' => $storage->value, 'coc' => $cocStatus->value]);

        return $storage === HealthStatus::Ok && $cocStatus !== HealthStatus::Down ? self::SUCCESS : self::FAILURE;
    }
}
