<?php

namespace App\Domain\Search\Data;

use App\Domain\Bases\Enums\BaseCategory;
use App\Domain\CocIntegration\Data\PlayerTag;

/**
 * The search text after `QueryParser` (specs/17 §3): a player tag, or the words left over plus the
 * Town Hall and category they named.
 */
final readonly class ParsedQuery
{
    /**
     * @param  list<ParsedFilterData>  $filters
     */
    public function __construct(
        public string $term,
        public ?int $thLevel = null,
        public ?BaseCategory $category = null,
        public array $filters = [],
        public ?PlayerTag $tag = null,
    ) {}
}
