<?php

namespace App\Console\Commands\Coc;

use App\Domain\CocIntegration\Services\CocHealthCheck;
use App\Domain\CocIntegration\Services\CocKeyPool;
use App\Support\Health\HealthStatus;
use Illuminate\Console\Command;

class CheckCocHealthCommand extends Command
{
    protected $signature = 'coc:check-health';

    protected $description = 'Probe every CoC API key and fail unless at least one works (specs/09 §3)';

    public function handle(CocHealthCheck $check, CocKeyPool $pool): int
    {
        $status = $check->run();

        foreach ($pool->status()->keys as $key) {
            $this->components->twoColumnDetail("key {$key->id}", $key->healthy ? 'healthy' : "unhealthy ({$key->reason})");
        }

        $this->components->twoColumnDetail('coc', $status->value);

        return $status === HealthStatus::Down ? self::FAILURE : self::SUCCESS;
    }
}
