<?php

namespace App\Domain\Search\Contracts;

use App\Domain\Search\Data\SearchSectionData;
use App\Domain\Search\Data\SourceQuery;
use App\Domain\Search\Enums\SearchType;
use App\Models\User;

/**
 * One kind of searchable content, implemented by the module that owns it and tagged
 * `SearchSource::TAG` in that module's provider, so Search never reads another module's tables
 * (specs/05 §2). Each source keeps the specs/17 §2 exclusions in its query, never as a filter on
 * the results (FR-SEARCH-6).
 */
interface SearchSource
{
    public const TAG = 'search.sources';

    public function type(): SearchType;

    /**
     * One page of hits, or null when the query gives this kind nothing to match on (players and
     * accounts need text; bases also match on filters alone).
     */
    public function search(SourceQuery $query, ?User $viewer): ?SearchSectionData;

    /**
     * Recomputes this kind's search vectors; returns the rows touched.
     */
    public function reindex(): int;
}
