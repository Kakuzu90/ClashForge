<?php

namespace App\Domain\Bases\Services;

use App\Domain\Bases\Data\FeedFiltersData;
use App\Domain\Bases\Enums\FeedSort;
use App\Domain\Bases\Queries\BaseListing;
use App\Domain\Bases\Queries\BaseSearchRanking;
use App\Domain\Search\Contracts\SearchSource;
use App\Domain\Search\Data\FacetCountData;
use App\Domain\Search\Data\SearchFacetsData;
use App\Domain\Search\Data\SearchSectionData;
use App\Domain\Search\Data\SourceQuery;
use App\Domain\Search\Enums\SearchType;
use App\Domain\Search\Services\TextQuery;
use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use stdClass;

/**
 * Bases in search (specs/17 §2, FR-SEARCH-3): the feed's listed bases and filters, matched on the
 * title, tags and description. "Best match" blends text rank, trending, recency and author
 * quality (specs/17 §5, weights in `platform.search.ranking`); the other sorts are the feed's.
 * Facet counts by Town Hall and category are cached per search for `facet_cache_ttl`.
 */
class BaseSearchSource implements SearchSource
{
    public function __construct(private readonly BaseListing $listing) {}

    public function type(): SearchType
    {
        return SearchType::Bases;
    }

    public function search(SourceQuery $query, ?User $viewer): SearchSectionData
    {
        $filters = $query->filters;
        $rows = $filters->sort === FeedSort::Relevance ? $this->byScore($query) : $this->bySort($query);
        $more = count($rows) > $query->limit;
        $rows = array_slice($rows, 0, $query->limit);
        $last = $rows === [] ? null : $rows[array_key_last($rows)];

        return new SearchSectionData(
            type: SearchType::Bases,
            items: $this->listing->cards($rows),
            hasMore: $more,
            nextCursor: $last === null ? null : $query->next($more, [BaseListing::sortValue($filters->sort, $last)], (int) $last->id),
            facets: $query->facets ? $this->facets($query) : null,
        );
    }

    public function reindex(): int
    {
        return DB::getDriverName() === 'pgsql'
            ? DB::update('UPDATE base_layouts SET search_vector = base_layout_search_vector(id, title, description)')
            : 0;
    }

    /**
     * Ordered by the blended score, computed once in a subquery so the keyset can compare it.
     *
     * @return list<stdClass>
     */
    private function byScore(SourceQuery $query): array
    {
        [$score, $bindings] = BaseSearchRanking::score($query);
        $inner = $this->matching($query, $query->filters)->select(BaseListing::COLUMNS)->selectRaw("{$score} AS search_score", $bindings);
        $hits = DB::query()->fromSub($inner, 'hits');

        if ($query->cursor !== null) {
            $value = $query->cursor->values[0] ?? '0';
            $hits->where(fn (Builder $q) => $q
                ->whereRaw('search_score < CAST(? AS double precision)', [$value])
                ->orWhere(fn (Builder $q) => $q->whereRaw('search_score = CAST(? AS double precision)', [$value])->where('id', '<', $query->cursor->id)));
        }

        return array_values($hits->orderByDesc('search_score')->orderByDesc('id')->limit($query->limit + 1)->get()->all());
    }

    /**
     * A feed sort, keyset-paged on its column like the feed.
     *
     * @return list<stdClass>
     */
    private function bySort(SourceQuery $query): array
    {
        $sort = $query->filters->sort;
        $column = $sort->column();
        $builder = $this->matching($query, $query->filters);

        if ($query->cursor !== null) {
            // `$column` comes from the FeedSort enum, never from input.
            $value = $sort === FeedSort::Trending ? 'CAST(? AS REAL)' : '?';
            $position = $query->cursor->values[0] ?? '';
            $builder->where(fn (Builder $q) => $q
                ->whereRaw("{$column} < {$value}", [$position])
                ->orWhere(fn (Builder $q) => $q->whereRaw("{$column} = {$value}", [$position])->where('base_layouts.id', '<', $query->cursor->id)));
        }

        return array_values($builder->orderByDesc($column)->orderByDesc('base_layouts.id')->limit($query->limit + 1)->get(BaseListing::COLUMNS)->all());
    }

    private function matching(SourceQuery $query, FeedFiltersData $filters, bool $th = true, bool $category = true): Builder
    {
        return $this->listing->query($filters, $th, $category)
            ->when($query->hasTerm(), fn (Builder $q) => $q->whereRaw('base_layouts.search_vector @@ '.TextQuery::ANY, TextQuery::bindings($query->term)));
    }

    private function facets(SourceQuery $query): SearchFacetsData
    {
        return Cache::remember('search:facets:'.hash('sha256', $query->signature), (int) config('platform.search.facet_cache_ttl'), fn (): SearchFacetsData => new SearchFacetsData(
            thLevels: $this->counts($this->matching($query, $query->filters, th: false), 'base_layouts.th_level'),
            categories: $this->counts($this->matching($query, $query->filters, category: false), 'base_layouts.category'),
        ));
    }

    /**
     * @return list<FacetCountData>
     */
    private function counts(Builder $builder, string $column): array
    {
        // `$column` is one of the two above, never input.
        return array_values($builder->selectRaw("{$column} AS value, count(*) AS hits")->groupBy($column)->orderByDesc('hits')->get()
            ->map(fn (stdClass $row): FacetCountData => new FacetCountData((string) $row->value, (int) $row->hits))
            ->all());
    }
}
