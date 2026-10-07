<?php

namespace App\Domain\Bases\Queries;

use App\Domain\Bases\Data\FeedFiltersData;
use App\Domain\Bases\Data\FeedPageData;
use App\Domain\Bases\Enums\FeedSort;
use App\Domain\Bases\Support\FeedCache;
use App\Domain\Bases\Support\FeedCursor;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Cache;

/**
 * The base feed (specs/17 §4, §6; FR-BASE-13): published, public bases whose author is not
 * suspended, banned or leaving (specs/12 §7), filtered and sorted, keyset-paged on the sort column
 * then the id. A page costs four queries: the bases, their authors (two) and credits, and covers.
 * Viewer-independent pages are cached under `FeedCache` (specs/21 §3).
 */
class BaseFeedQuery
{
    public function __construct(private readonly BaseListing $listing) {}

    /**
     * Whether a `cursor` from the query string belongs to this feed and may be loaded: signed by
     * us for these filters, and within the anonymous page cap (specs/17 §4). Form Requests reject
     * anything else as a field error, never a 500.
     */
    public static function acceptsCursor(string $cursor, FeedFiltersData $filters, bool $anonymous): bool
    {
        $decoded = FeedCursor::decode($cursor, $filters);

        return $decoded !== null && (! $anonymous || $decoded->page <= (int) config('bases.feed.max_pages'));
    }

    /**
     * @param  string|null  $cursor  one `acceptsCursor` passed; anything else starts at page 1
     */
    public function page(FeedFiltersData $filters, ?string $cursor, bool $anonymous): FeedPageData
    {
        $cursor = $cursor === null ? null : FeedCursor::decode($cursor, $filters);
        $number = $cursor->page ?? 1;
        $build = fn (): FeedPageData => $this->build($filters, $cursor, $number);

        $page = $filters->cacheable() && $number <= (int) config('bases.feed.cache_max_page')
            ? Cache::remember(FeedCache::key($filters, $number, $cursor === null ? null : "{$cursor->value}|{$cursor->id}"), FeedCache::ttl($filters), $build)
            : $build();

        // The anonymous cap applies after the cache, which signed-in viewers share.
        if ($anonymous && $number >= (int) config('bases.feed.max_pages')) {
            return new FeedPageData($page->cards, null, $page->page);
        }

        return $page;
    }

    private function build(FeedFiltersData $filters, ?FeedCursor $cursor, int $number): FeedPageData
    {
        $perPage = (int) config('bases.feed.per_page');
        $column = $filters->sort->column();
        $query = $this->listing->query($filters);

        if ($cursor !== null) {
            // `$column` comes from the FeedSort enum, never from input. The trending score is a
            // `real`: the cursor holds it as text, cast back to `real` so it compares exactly.
            $value = $filters->sort === FeedSort::Trending ? 'CAST(? AS REAL)' : '?';
            $query->where(fn (Builder $q) => $q
                ->whereRaw("{$column} < {$value}", [$cursor->value])
                ->orWhere(fn (Builder $q) => $q->whereRaw("{$column} = {$value}", [$cursor->value])->where('base_layouts.id', '<', $cursor->id)));
        }

        $rows = $query->orderByDesc($column)->orderByDesc('base_layouts.id')->limit($perPage + 1)->get(BaseListing::COLUMNS);

        $more = $rows->count() > $perPage;
        $rows = $rows->take($perPage)->values();
        $last = $rows->last();

        return new FeedPageData(
            cards: $this->listing->cards(array_values($rows->all())),
            nextCursor: $more && $last !== null ? FeedCursor::for($filters, BaseListing::sortValue($filters->sort, $last), (int) $last->id, $number + 1) : null,
            page: $number,
        );
    }
}
