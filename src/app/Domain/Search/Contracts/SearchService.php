<?php

namespace App\Domain\Search\Contracts;

use App\Domain\Search\Data\SearchQuery;
use App\Domain\Search\Data\SearchResultsData;
use App\Domain\Search\Enums\SearchType;
use App\Models\User;

/**
 * The only way into search (FR-SEARCH-5, specs/17 §1). Callers never write SQL or an engine query,
 * so moving to a search engine (specs/17 §7) swaps the implementation bound here.
 */
interface SearchService
{
    public function search(SearchQuery $query, ?User $viewer): SearchResultsData;

    /**
     * Whether the text gives the search something to look for: a player tag, a filter it names, or
     * a word that is not only excluded (`-word`). Anything else is a field error, never a scan.
     */
    public function hasSearchableText(SearchQuery $query): bool;

    /**
     * Whether `$query->cursor` belongs to this search and may be loaded: ours, for this query, and
     * within the anonymous page cap (specs/17 §4).
     */
    public function acceptsCursor(SearchQuery $query, bool $anonymous): bool;

    /**
     * Rebuilds the search data of one kind of result, or all of them; returns the rows touched.
     */
    public function reindex(?SearchType $type = null): int;
}
