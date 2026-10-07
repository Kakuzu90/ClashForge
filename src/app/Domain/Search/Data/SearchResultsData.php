<?php

namespace App\Domain\Search\Data;

use App\Domain\Bases\Data\FeedFiltersData;
use App\Domain\Search\Enums\SearchType;
use Spatie\LaravelData\Data;

/**
 * A search's answer (specs/17 §1). `filters` are the base filters in force, parsed ones included,
 * and `parsed` the ones read from the text. A tag search (FR-SEARCH-2) sets `tag`, and
 * `accountUlid` when an account with that tag may be shown, so the page can send the visitor
 * there; otherwise it has no sections.
 */
class SearchResultsData extends Data
{
    /**
     * @param  list<ParsedFilterData>  $parsed
     * @param  array<string, SearchSectionData>  $sections  by SearchType value
     */
    public function __construct(
        public string $term,
        public SearchType $type,
        public FeedFiltersData $filters,
        public array $parsed,
        public array $sections,
        public ?string $tag = null,
        public ?string $accountUlid = null,
    ) {}

    public function section(SearchType $type): ?SearchSectionData
    {
        return $this->sections[$type->value] ?? null;
    }
}
