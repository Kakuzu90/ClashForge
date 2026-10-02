<?php

namespace App\Domain\CocIntegration\Data;

use App\Domain\CocIntegration\Enums\CocCircuitReason;
use App\Domain\CocIntegration\Enums\CocCircuitState;
use Carbon\CarbonImmutable;

/**
 * The breaker as other modules see it (CocApiStatus): what is disabled and until when. `openUntil`
 * is the next probe time, or the end of maintenance when the API said.
 */
final readonly class CocApiStateData
{
    public function __construct(
        public CocCircuitState $state,
        public ?CocCircuitReason $reason,
        public ?CarbonImmutable $openUntil,
    ) {}
}
