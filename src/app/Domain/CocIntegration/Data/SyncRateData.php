<?php

namespace App\Domain\CocIntegration\Data;

/**
 * Sync attempts in the last `windowMinutes` and how many succeeded (specs/20 §6). `rate` is null
 * when nothing was attempted.
 */
final readonly class SyncRateData
{
    public function __construct(
        public int $windowMinutes,
        public int $attempts,
        public int $successes,
        public ?float $rate,
    ) {}
}
