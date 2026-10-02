<?php

namespace App\Domain\CocIntegration\Services;

use App\Domain\CocIntegration\Support\HttpCocApiClient;
use App\Support\Health\HealthChecker;
use App\Support\Health\HealthStatus;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Log;

/**
 * Probes every key of the pool and records the result for /health (specs/09 §3, specs/23 §5: an
 * egress IP change is seen within one 5-minute run). Ok when every key works, degraded when some
 * do or the API did not answer, down when none does. `coc` is never a required check: an API
 * outage degrades the platform, it does not take it down (NFR-AVAIL-2).
 */
class CocHealthCheck
{
    public function __construct(
        private readonly CocKeyPool $pool,
        private readonly HealthChecker $health,
    ) {}

    public function run(): HealthStatus
    {
        $status = config('coc.driver') === 'http' ? $this->probeKeys() : $this->fakeStatus();
        $this->health->recordCoc($status);

        return $status;
    }

    private function probeKeys(): HealthStatus
    {
        $keys = $this->pool->keys();

        if ($keys === []) {
            Log::critical('coc.keys_all_unhealthy', ['keys' => 0]);

            return HealthStatus::Down;
        }

        $client = App::make(HttpCocApiClient::class);
        $working = $refused = 0;

        foreach ($keys as $key) {
            $result = $client->probe($key);

            if ($result === true) {
                $this->pool->markHealthy($key);
                $working++;
            } elseif ($result === false) {
                $refused++;
            }
        }

        Log::info('coc.check_health', ['keys' => count($keys), 'working' => $working, 'refused' => $refused]);

        if ($working === count($keys)) {
            return HealthStatus::Ok;
        }

        return $refused === count($keys) ? HealthStatus::Down : HealthStatus::Degraded;
    }

    /**
     * The fake needs no keys; in production it is a misconfiguration, so the check says so.
     */
    private function fakeStatus(): HealthStatus
    {
        if (App::isProduction()) {
            Log::critical('coc.fake_driver_in_production');

            return HealthStatus::Down;
        }

        return HealthStatus::Ok;
    }
}
