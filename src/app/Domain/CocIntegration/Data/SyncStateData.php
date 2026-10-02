<?php

namespace App\Domain\CocIntegration\Data;

use App\Domain\CocIntegration\Enums\SyncTier;
use Carbon\CarbonImmutable;

/**
 * A resource's schedule after a sync attempt. `nextDueAt` null means it is no longer scheduled:
 * frozen and out of retries.
 */
final readonly class SyncStateData
{
    public function __construct(
        public SyncTier $tier,
        public int $consecutiveFailures,
        public ?CarbonImmutable $nextDueAt,
    ) {}

    public function stopped(): bool
    {
        return $this->nextDueAt === null;
    }
}
