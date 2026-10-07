<?php

namespace App\Domain\Search\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Base search facets (FR-SEARCH-3): counts by Town Hall and by category. Each ignores its own
 * filter, so picking another level or category shows what it would find.
 */
#[TypeScript]
class SearchFacetsData extends Data
{
    /**
     * @param  list<FacetCountData>  $thLevels
     * @param  list<FacetCountData>  $categories
     */
    public function __construct(
        public array $thLevels,
        public array $categories,
    ) {}
}
