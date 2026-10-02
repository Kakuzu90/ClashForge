<?php

namespace App\Domain\CocIntegration\Data;

use Carbon\CarbonImmutable;

/**
 * One key of the pool, by id only (the first characters of the token's sha256).
 */
final readonly class CocKeyStatusData
{
    public function __construct(
        public string $id,
        public bool $healthy,
        public ?string $reason,
        public ?CarbonImmutable $unhealthySince,
    ) {}
}
