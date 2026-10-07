<?php

namespace App\Domain\Search\Data;

use App\Domain\Bases\Data\FeedFiltersData;
use Carbon\CarbonImmutable;

/**
 * What one source searches for (specs/17 §1): the text left after parsing, the base filters and
 * sort with the parsed ones merged in, the Town Hall the text named (accounts filter on it too),
 * how many hits, and where the page starts. `paged` asks for a next-page cursor; a grouped "All"
 * search only wants to know whether there are more. `now` is fixed for a whole result list, so
 * a recency-weighted score does not drift between pages.
 */
final readonly class SourceQuery
{
    public function __construct(
        public string $term,
        public FeedFiltersData $filters,
        public ?int $thLevel,
        public int $limit,
        public bool $paged,
        public string $signature,
        public CarbonImmutable $now,
        public ?SearchCursor $cursor = null,
        public bool $facets = false,
    ) {}

    public function hasTerm(): bool
    {
        return $this->term !== '';
    }

    /**
     * The next page's cursor, or null on the last page and for a grouped search.
     *
     * @param  list<string>  $values  the last hit's sort values, as text that round-trips exactly
     */
    public function next(bool $more, array $values, int $id): ?string
    {
        return $more && $this->paged ? SearchCursor::for($this->signature, $values, $id, ($this->cursor->page ?? 1) + 1, $this->now) : null;
    }
}
