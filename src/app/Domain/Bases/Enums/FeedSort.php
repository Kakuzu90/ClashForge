<?php

namespace App\Domain\Bases\Enums;

use App\Support\Enums\Concerns\EnumHelpers;
use App\Support\Enums\Contracts\HasLabelAndColor;

/**
 * Feed orders (FR-BASE-13). Each is keyset-paged on its column, then the base id.
 */
enum FeedSort: string implements HasLabelAndColor
{
    use EnumHelpers;

    case Trending = 'trending';
    case Newest = 'new';
    case MostLiked = 'liked';
    case MostCopied = 'copied';

    public function label(): string
    {
        return match ($this) {
            self::Trending => 'Trending',
            self::Newest => 'New',
            self::MostLiked => 'Most liked',
            self::MostCopied => 'Most copied',
        };
    }

    public function color(): string
    {
        return 'text-muted';
    }

    /**
     * The qualified column the sort orders by.
     */
    public function column(): string
    {
        return match ($this) {
            self::Trending => 'base_metrics.trending_score',
            self::Newest => 'base_layouts.published_at',
            self::MostLiked => 'base_metrics.likes_count',
            self::MostCopied => 'base_metrics.copies_count',
        };
    }
}
