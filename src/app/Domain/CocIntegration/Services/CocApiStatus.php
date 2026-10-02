<?php

namespace App\Domain\CocIntegration\Services;

use App\Domain\CocIntegration\Data\CocApiStateData;
use App\Domain\CocIntegration\Enums\CocCircuitState;
use App\Domain\CocIntegration\Support\CircuitBreaker;
use App\Domain\CocIntegration\Support\RateBudget;

/**
 * The API's availability for other modules (specs/05 §2): the site banner and the attach, verify
 * and refresh buttons read it (specs/09 §7 degradation contract), and the sync scheduler sizes its
 * batch from the background budget (specs/09 §6).
 */
class CocApiStatus
{
    public function __construct(
        private readonly CircuitBreaker $breaker,
        private readonly RateBudget $budget,
    ) {}

    public function state(): CocApiStateData
    {
        return $this->breaker->state();
    }

    /**
     * False only while the breaker is open; half-open counts as available, since the next call is
     * the probe.
     */
    public function isAvailable(): bool
    {
        return $this->breaker->state()->state !== CocCircuitState::Open;
    }

    public function backgroundBudgetRemaining(): int
    {
        return $this->budget->backgroundRemainingThisMinute();
    }
}
