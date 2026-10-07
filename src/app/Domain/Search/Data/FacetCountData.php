<?php

namespace App\Domain\Search\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * How many hits one filter value would leave (specs/17 §3 facets).
 */
#[TypeScript]
class FacetCountData extends Data
{
    public function __construct(
        public string $value,
        public int $count,
    ) {}
}
