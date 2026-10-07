<?php

namespace App\Domain\Bases\Queries;

use App\Domain\Auth\Services\UserStatusService;
use App\Domain\Bases\Data\BaseCardData;
use App\Domain\Bases\Data\FeedFiltersData;
use App\Domain\Bases\Data\FeedPageData;
use App\Domain\Bases\Enums\BaseCategory;
use App\Domain\Bases\Enums\BaseStatus;
use App\Domain\Bases\Enums\BaseVisibility;
use App\Domain\Bases\Enums\FeedSort;
use App\Domain\Bases\Models\BaseLayout;
use App\Domain\Bases\Support\FeedCache;
use App\Domain\Bases\Support\FeedCursor;
use App\Domain\Media\Enums\MediaCollection;
use App\Domain\Media\Enums\VariantName;
use App\Domain\Media\Services\MediaReadService;
use App\Domain\PlayerAccounts\Services\AccountCredits;
use App\Domain\Users\Services\AuthorDirectory;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Cache;
use stdClass;

/**
 * The base feed (specs/17 §4, §6; FR-BASE-13): published, public bases whose author is not
 * suspended, banned or leaving (specs/12 §7), filtered and sorted, keyset-paged on the sort column
 * then the id. A page costs four queries: the bases, their authors (two) and credits, and covers.
 * Viewer-independent pages are cached under `FeedCache` (specs/21 §3).
 */
class BaseFeedQuery
{
    public function __construct(
        private readonly UserStatusService $statuses,
        private readonly AuthorDirectory $authors,
        private readonly AccountCredits $credits,
        private readonly MediaReadService $media,
    ) {}

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

        $query = BaseLayout::query()->toBase()
            ->join('base_metrics', 'base_metrics.base_layout_id', '=', 'base_layouts.id')
            ->where('base_layouts.status', BaseStatus::Published->value)
            ->where('base_layouts.visibility', BaseVisibility::Public->value)
            ->whereNull('base_layouts.deleted_at')
            ->whereNotIn('base_layouts.user_id', $this->statuses->hiddenAuthorIds())
            ->when($filters->thMin !== null, fn (Builder $q) => $q->whereBetween('base_layouts.th_level', [$filters->thMin, $filters->thMax]))
            ->when($filters->category !== null, fn (Builder $q) => $q->where('base_layouts.category', $filters->category?->value))
            ->when($filters->minLikes !== null, fn (Builder $q) => $q->where('base_metrics.likes_count', '>=', $filters->minLikes))
            ->when($filters->hasVideo, fn (Builder $q) => $q->where('base_layouts.has_video', true))
            ->when($filters->tag !== null, fn (Builder $q) => $q->whereExists(fn (Builder $tags) => $tags->selectRaw('1')
                ->from('base_layout_tag')
                ->join('base_tags', 'base_tags.id', '=', 'base_layout_tag.base_tag_id')
                ->whereColumn('base_layout_tag.base_layout_id', 'base_layouts.id')
                ->where('base_tags.slug', $filters->tag)));

        if ($cursor !== null) {
            // `$column` comes from the FeedSort enum, never from input. The trending score is a
            // `real`: the cursor holds it as text, cast back to `real` so it compares exactly.
            $value = $filters->sort === FeedSort::Trending ? 'CAST(? AS REAL)' : '?';
            $query->where(fn (Builder $q) => $q
                ->whereRaw("{$column} < {$value}", [$cursor->value])
                ->orWhere(fn (Builder $q) => $q->whereRaw("{$column} = {$value}", [$cursor->value])->where('base_layouts.id', '<', $cursor->id)));
        }

        $rows = $query->orderByDesc($column)->orderByDesc('base_layouts.id')->limit($perPage + 1)->get([
            'base_layouts.id', 'base_layouts.ulid', 'base_layouts.slug', 'base_layouts.title', 'base_layouts.th_level',
            'base_layouts.category', 'base_layouts.has_video', 'base_layouts.user_id', 'base_layouts.coc_account_id',
            'base_layouts.published_at', 'base_metrics.likes_count', 'base_metrics.copies_count', 'base_metrics.views_count',
            'base_metrics.trending_score',
        ]);

        $more = $rows->count() > $perPage;
        $rows = $rows->take($perPage)->values();
        $last = $rows->last();

        return new FeedPageData(
            cards: $this->cards(array_values($rows->all())),
            nextCursor: $more && $last !== null ? FeedCursor::for($filters, $this->sortValue($filters->sort, $last), (int) $last->id, $number + 1) : null,
            page: $number,
        );
    }

    /**
     * @param  list<stdClass>  $rows
     * @return list<BaseCardData>
     */
    private function cards(array $rows): array
    {
        if ($rows === []) {
            return [];
        }

        $authors = $this->authors->forUsers(array_map(fn (stdClass $row): int => (int) $row->user_id, $rows));
        $credits = $this->credits->creditsFor(array_values(array_filter(array_map(fn (stdClass $row): ?int => $row->coc_account_id === null ? null : (int) $row->coc_account_id, $rows))));
        $covers = $this->media->firstReadyVariants(new BaseLayout, array_map(fn (stdClass $row): int => (int) $row->id, $rows), [
            [MediaCollection::BaseScreenshot, VariantName::Card],
            [MediaCollection::BaseVideo, VariantName::Poster],
        ]);

        $cards = [];
        foreach ($rows as $row) {
            $author = $authors[(int) $row->user_id] ?? null;

            // An author row always exists for a listed base; skip rather than guess if it does not.
            if ($author === null) {
                continue;
            }

            $cards[] = new BaseCardData(
                ulid: (string) $row->ulid,
                slug: (string) $row->slug,
                title: (string) $row->title,
                thLevel: (int) $row->th_level,
                category: BaseCategory::from((string) $row->category),
                hasVideo: (bool) $row->has_video,
                cover: $covers[(int) $row->id] ?? null,
                likes: (int) $row->likes_count,
                copies: (int) $row->copies_count,
                views: (int) $row->views_count,
                author: $author['author'],
                credit: $author['accountsPublic'] && $row->coc_account_id !== null ? ($credits[(int) $row->coc_account_id] ?? null) : null,
            );
        }

        return $cards;
    }

    /**
     * The last card's sort value as the cursor keeps it: the stored text for times, and enough
     * digits for a float to round-trip exactly.
     */
    private function sortValue(FeedSort $sort, stdClass $row): string
    {
        $value = match ($sort) {
            FeedSort::Trending => $row->trending_score,
            FeedSort::Newest => $row->published_at,
            FeedSort::MostLiked => $row->likes_count,
            FeedSort::MostCopied => $row->copies_count,
        };

        return is_float($value) ? sprintf('%.17g', $value) : (string) $value;
    }
}
