<?php

namespace App\Console\Commands\Platform;

use App\Support\Health\HealthChecker;
use Illuminate\Console\Command;

class HeartbeatCommand extends Command
{
    protected $signature = 'platform:heartbeat';

    protected $description = 'Record that the scheduler ran (read by /health)';

    public function handle(HealthChecker $health): int
    {
        $health->beat();

        return self::SUCCESS;
    }
}
