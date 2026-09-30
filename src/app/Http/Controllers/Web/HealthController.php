<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Support\Health\HealthChecker;
use App\Support\Health\HealthStatus;
use Illuminate\Http\JsonResponse;

/**
 * Uptime probe (NFR-OBS-4). Public, so it reports only a status word per check: no error text,
 * hosts, versions or timings.
 */
class HealthController extends Controller
{
    public function __invoke(HealthChecker $health): JsonResponse
    {
        $checks = $health->checks();
        $overall = $health->overall($checks);

        return response()->json([
            'status' => $overall->value,
            'checks' => array_map(fn (HealthStatus $status): string => $status->value, $checks),
        ], $overall === HealthStatus::Down ? 503 : 200, ['Cache-Control' => 'no-store']);
    }
}
