<?php

namespace App\Domain\CocIntegration\Data;

/**
 * Key pool state for /health and, later, the system health page (specs/09 §3, specs/20 §6).
 */
final readonly class KeyPoolStatusData
{
    /**
     * @param  list<CocKeyStatusData>  $keys
     */
    public function __construct(
        public int $total,
        public int $healthy,
        public array $keys,
    ) {}
}
