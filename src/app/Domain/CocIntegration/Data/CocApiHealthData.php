<?php

namespace App\Domain\CocIntegration\Data;

use App\Domain\CocIntegration\Enums\CocCircuitReason;
use App\Domain\CocIntegration\Enums\CocCircuitState;
use Carbon\CarbonImmutable;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * The dashboard's Clash of Clans API panel (FR-ADMIN-5): the breaker, the key pool and the request
 * log over the last `windowHours`. `calls` are requests that left for the API; cache hits are
 * counted apart. A failure is what the breaker counts (specs/09 §7): a timeout or a 5xx, maintenance
 * included; 403 and 429 belong to the key pool and the budget, and a 404 is an answer. A malformed
 * 200 is logged as a 200, so it is not counted here. `failureRate` is null with no calls.
 * The sync fields are account syncs over `syncWindowMinutes` (specs/20 §6: flagged under
 * `syncAlert`), `syncSuccessRate` null with no attempts; `syncStopped` counts accounts frozen and out
 * of retries.
 */
#[TypeScript]
class CocApiHealthData extends Data
{
    public function __construct(
        public CocCircuitState $state,
        public ?CocCircuitReason $reason,
        public ?CarbonImmutable $openUntil,
        public int $keysHealthy,
        public int $keysTotal,
        public int $windowHours,
        public int $calls,
        public int $cacheHits,
        public int $failures,
        public ?float $failureRate,
        public ?string $topError,
        public int $syncWindowMinutes,
        public int $syncAttempts,
        public int $syncSuccesses,
        public ?float $syncSuccessRate,
        public float $syncAlert,
        public bool $syncBelowAlert,
        public int $syncStopped,
    ) {}
}
