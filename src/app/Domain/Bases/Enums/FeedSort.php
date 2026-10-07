<?php

namespace App\Domain\Bases\Enums;

use App\Support\Enums\Concerns\EnumHelpers;
use App\Support\Enums\Contracts\HasLabelAndColor;

/**
 * Feed orders (FR-BASE-13). Each is keyset-paged on its column, then the base id. `relevance` is
 * search only (specs/17 §5): the feeds offer the other four.
 */
enum FeedSort: string implements HasLabelAndColor
{
    use EnumHelpers;

    case Trending = 'trending';
    case Newest = 'new';
    case MostLiked = 'liked';
    case MostCopied = 'copied';
    case Relevance = 'relevance';

    public function label(): string
    {
        return match ($this) {
            self::Trending => 'Trending',
            self::Newest => 'New',
            self::MostLiked => 'Most liked',
            self::MostCopied => 'Most copied',
            self::Relevance => 'Best match',
        };
    }

    public function color(): string
    {
        return 'text-muted';
    }

    /**
     * The sorts a feed offers.
     *
     * @return list<self>
     */
    public static function feed(): array
    {
        return [self::Trending, self::Newest, self::MostLiked, self::MostCopied];
    }

    /**
     * The qualified column the sort orders by; relevance orders by the search score.
     */
    public function column(): string
    {
        return match ($this) {
            self::Trending => 'base_metrics.trending_score',
            self::Newest => 'base_layouts.published_at',
            self::MostLiked => 'base_metrics.likes_count',
            self::MostCopied => 'base_metrics.copies_count',
            self::Relevance => 'search_score',
        };
    }
}
