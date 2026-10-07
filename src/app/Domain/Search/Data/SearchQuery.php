<?php

namespace App\Domain\Search\Data;

use App\Domain\Bases\Data\FeedFiltersData;
use App\Domain\Search\Enums\SearchType;

/**
 * A search as the visitor asked for it (specs/17 §1): the text as typed, which results, the base
 * filters and sort set explicitly, and the next page's cursor. Filters the text implies are parsed
 * by the search itself, so they can be shown and removed (specs/17 §3).
 */
final readonly class SearchQuery
{
    public function __construct(
        public string $term,
        public SearchType $type = SearchType::All,
        public FeedFiltersData $filters = new FeedFiltersData,
        public ?string $cursor = null,
    ) {}
}
