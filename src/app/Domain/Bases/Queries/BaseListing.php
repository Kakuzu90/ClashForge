<?php

namespace App\Domain\Bases\Queries;

use App\Domain\Auth\Services\UserStatusService;
use App\Domain\Bases\Data\BaseCardData;
use App\Domain\Bases\Data\FeedFiltersData;
use App\Domain\Bases\Enums\BaseCategory;
use App\Domain\Bases\Enums\BaseStatus;
use App\Domain\Bases\Enums\BaseVisibility;
use App\Domain\Bases\Enums\FeedSort;
use App\Domain\Bases\Models\BaseLayout;
use App\Domain\Media\Enums\MediaCollection;
use App\Domain\Media\Enums\VariantName;
use App\Domain\Media\Services\MediaReadService;
use App\Domain\PlayerAccounts\Services\AccountCredits;
use App\Domain\Users\Services\AuthorDirectory;
use Illuminate\Database\Query\Builder;
use stdClass;

/**
 * What the feed and base search share (P3-03, P3-05): the bases anyone may list (published,
 * public, not deleted, the author not suspended, banned or leaving, specs/12 §7) with the
 * FR-BASE-13 filters, and the cards built from those rows.
 */
class BaseListing
{
    /**
     * The columns `cards()` and the keyset need.
     *
     * @var list<string>
     */
    public const COLUMNS = [
        'base_layouts.id', 'base_layouts.ulid', 'base_layouts.slug', 'base_layouts.title', 'base_layouts.th_level',
        'base_layouts.category', 'base_layouts.has_video', 'base_layouts.user_id', 'base_layouts.coc_account_id',
        'base_layouts.published_at', 'base_metrics.likes_count', 'base_metrics.copies_count', 'base_metrics.views_count',
        'base_metrics.trending_score',
    ];

    public function __construct(
        private readonly UserStatusService $statuses,
        private readonly AuthorDirectory $authors,
        private readonly AccountCredits $credits,
        private readonly MediaReadService $media,
    ) {}

    /**
     * Listed bases joined with their metrics, filtered. A facet count leaves out its own filter
     * (`$th`, `$category` false).
     */
    public function query(FeedFiltersData $filters, bool $th = true, bool $category = true): Builder
    {
        return BaseLayout::query()->toBase()
            ->join('base_metrics', 'base_metrics.base_layout_id', '=', 'base_layouts.id')
            ->where('base_layouts.status', BaseStatus::Published->value)
            ->where('base_layouts.visibility', BaseVisibility::Public->value)
            ->whereNull('base_layouts.deleted_at')
            ->whereNotIn('base_layouts.user_id', $this->statuses->hiddenAuthorIds())
            ->when($th && $filters->thMin !== null, fn (Builder $q) => $q->whereBetween('base_layouts.th_level', [$filters->thMin, $filters->thMax]))
            ->when($category && $filters->category !== null, fn (Builder $q) => $q->where('base_layouts.category', $filters->category?->value))
            ->when($filters->minLikes !== null, fn (Builder $q) => $q->where('base_metrics.likes_count', '>=', $filters->minLikes))
            ->when($filters->hasVideo, fn (Builder $q) => $q->where('base_layouts.has_video', true))
            ->when($filters->tag !== null, fn (Builder $q) => $q->whereExists(fn (Builder $tags) => $tags->selectRaw('1')
                ->from('base_layout_tag')
                ->join('base_tags', 'base_tags.id', '=', 'base_layout_tag.base_tag_id')
                ->whereColumn('base_layout_tag.base_layout_id', 'base_layouts.id')
                ->where('base_tags.slug', $filters->tag)));
    }

    /**
     * @param  list<stdClass>  $rows
     * @return list<BaseCardData>
     */
    public function cards(array $rows): array
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
    public static function sortValue(FeedSort $sort, stdClass $row): string
    {
        $value = match ($sort) {
            FeedSort::Trending => $row->trending_score,
            FeedSort::Newest => $row->published_at,
            FeedSort::MostLiked => $row->likes_count,
            FeedSort::MostCopied => $row->copies_count,
            FeedSort::Relevance => $row->search_score,
        };

        return is_float($value) ? sprintf('%.17g', $value) : (string) $value;
    }
}
