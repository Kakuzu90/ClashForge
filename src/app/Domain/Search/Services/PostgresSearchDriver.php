<?php

namespace App\Domain\Search\Services;

use App\Domain\Bases\Data\FeedFiltersData;
use App\Domain\Search\Contracts\SearchService;
use App\Domain\Search\Contracts\SearchSource;
use App\Domain\Search\Contracts\TagLookup;
use App\Domain\Search\Data\ParsedFilterData;
use App\Domain\Search\Data\ParsedQuery;
use App\Domain\Search\Data\SearchCursor;
use App\Domain\Search\Data\SearchQuery;
use App\Domain\Search\Data\SearchResultsData;
use App\Domain\Search\Data\SearchSectionData;
use App\Domain\Search\Data\SourceQuery;
use App\Domain\Search\Enums\SearchType;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Date;

/**
 * Search v1 on Postgres full text (specs/17 §3). Parses the text, short-circuits a player tag, then
 * asks each source for its hits. A grouped search reads a few of each kind; a single kind reads a
 * keyset page. Anonymous first pages are cached for a minute under `search:{signature}` (specs/21
 * §3); the short TTL is the invalidation.
 */
class PostgresSearchDriver implements SearchService
{
    public function __construct(
        private readonly QueryParser $parser,
        private readonly TagLookup $tags,
    ) {}

    public function search(SearchQuery $query, ?User $viewer): SearchResultsData
    {
        $parsed = $this->parse($query->term);

        if ($parsed->tag !== null) {
            return new SearchResultsData($query->term, $query->type, $query->filters, [], [], $parsed->tag->value, $this->tags->findAccount($parsed->tag, $viewer));
        }

        [$filters, $chips] = $this->merge($query->filters, $parsed);

        if (trim($query->term) === '') {
            return new SearchResultsData('', $query->type, $filters, [], []);
        }

        $signature = $this->signature($query->type, $parsed, $filters);
        $cursor = $query->cursor === null ? null : SearchCursor::decode($query->cursor, $signature);
        $build = fn (): array => $this->sections($query->type, $parsed, $filters, $signature, $cursor, $viewer);

        $sections = $viewer === null && $cursor === null
            ? Cache::remember('search:'.hash('sha256', $signature), (int) config('platform.search.cache_ttl'), $build)
            : $build();

        // The anonymous page cap applies after the cache, like the feed's.
        if ($viewer === null && $cursor !== null && $cursor->page >= (int) config('platform.search.max_pages')) {
            $sections = array_map(fn (SearchSectionData $section): SearchSectionData => new SearchSectionData($section->type, $section->items, false, null, $section->facets), $sections);
        }

        return new SearchResultsData($query->term, $query->type, $filters, $chips, $sections);
    }

    public function hasSearchableText(SearchQuery $query): bool
    {
        $parsed = $this->parse($query->term);

        return $parsed->tag !== null || $parsed->filters !== [] || $parsed->term !== '';
    }

    public function acceptsCursor(SearchQuery $query, bool $anonymous): bool
    {
        if ($query->cursor === null || $query->type === SearchType::All) {
            return false;
        }

        $parsed = $this->parse($query->term);
        [$filters] = $this->merge($query->filters, $parsed);
        $cursor = SearchCursor::decode($query->cursor, $this->signature($query->type, $parsed, $filters));

        return $cursor !== null && (! $anonymous || $cursor->page <= (int) config('platform.search.max_pages'));
    }

    public function reindex(?SearchType $type = null): int
    {
        $count = 0;
        foreach ($this->sources() as $source) {
            if ($type === null || $type === SearchType::All || $source->type() === $type) {
                $count += $source->reindex();
            }
        }

        return $count;
    }

    /**
     * The parsed text, with leftover words that give an index nothing to look up dropped
     * (`TextQuery::indexable`), so no source ever scans a whole table for them.
     */
    private function parse(string $term): ParsedQuery
    {
        $parsed = $this->parser->parse($term);

        return $parsed->term === '' || TextQuery::indexable($parsed->term)
            ? $parsed
            : new ParsedQuery('', $parsed->thLevel, $parsed->category, $parsed->filters, $parsed->tag);
    }

    /**
     * @return array<string, SearchSectionData>
     */
    private function sections(SearchType $type, ParsedQuery $parsed, FeedFiltersData $filters, string $signature, ?SearchCursor $cursor, ?User $viewer): array
    {
        $paged = $type !== SearchType::All;
        $now = $cursor->at ?? Date::now()->toImmutable();
        $sources = $this->sources();
        $sections = [];

        foreach ($type->sources() as $kind) {
            $source = $sources[$kind->value] ?? null;
            $section = $source?->search(new SourceQuery(
                term: $parsed->term,
                filters: $filters,
                thLevel: $parsed->thLevel,
                limit: $paged ? (int) config('platform.search.per_page') : (int) config("platform.search.group_size.{$kind->value}"),
                paged: $paged,
                signature: $signature,
                now: $now,
                cursor: $cursor,
                facets: $paged && $kind === SearchType::Bases,
            ), $viewer);

            if ($section !== null) {
                $sections[$kind->value] = $section;
            }
        }

        return $sections;
    }

    /**
     * The explicit filters, with the parsed Town Hall and category filling the ones not set; only
     * filters that took effect become chips.
     *
     * @return array{FeedFiltersData, list<ParsedFilterData>}
     */
    private function merge(FeedFiltersData $filters, ParsedQuery $parsed): array
    {
        $chips = [];
        $th = $filters->thMin === null && $parsed->thLevel !== null;
        $category = $filters->category === null && $parsed->category !== null;

        foreach ($parsed->filters as $chip) {
            if (($chip->key === 'th' && $th) || ($chip->key === 'category' && $category)) {
                $chips[] = $chip;
            }
        }

        return [new FeedFiltersData(
            thMin: $th ? $parsed->thLevel : $filters->thMin,
            thMax: $th ? $parsed->thLevel : $filters->thMax,
            category: $category ? $parsed->category : $filters->category,
            tag: $filters->tag,
            minLikes: $filters->minLikes,
            hasVideo: $filters->hasVideo,
            sort: $filters->sort,
        ), $chips];
    }

    private function signature(SearchType $type, ParsedQuery $parsed, FeedFiltersData $filters): string
    {
        return implode('|', ['v1', $type->value, mb_strtolower($parsed->term), $parsed->thLevel ?? '-', $filters->signature()]);
    }

    /**
     * @return array<string, SearchSource> by type
     */
    private function sources(): array
    {
        $sources = [];
        foreach (app()->tagged(SearchSource::TAG) as $source) {
            if ($source instanceof SearchSource) {
                $sources[$source->type()->value] = $source;
            }
        }

        return $sources;
    }
}
