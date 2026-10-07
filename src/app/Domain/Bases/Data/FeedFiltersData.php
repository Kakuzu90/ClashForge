<?php

namespace App\Domain\Bases\Data;

use App\Domain\Bases\Enums\BaseCategory;
use App\Domain\Bases\Enums\FeedSort;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * A feed's filters and sort (FR-BASE-13), as parsed from the query string and sent back to the page
 * so it can show them. A Town Hall filter is a range: one level, or the signed-in default of the
 * featured account's level ±1 (P3-03).
 */
#[TypeScript]
class FeedFiltersData extends Data
{
    public function __construct(
        public ?int $thMin = null,
        public ?int $thMax = null,
        public ?BaseCategory $category = null,
        public ?string $tag = null,
        public ?int $minLikes = null,
        public bool $hasVideo = false,
        public FeedSort $sort = FeedSort::Trending,
    ) {}

    /**
     * Stable across requests: binds a cursor to the feed it came from and names cached pages.
     */
    public function signature(): string
    {
        return implode(':', [
            $this->sort->value,
            $this->thMin === null ? 'all' : "th{$this->thMin}-{$this->thMax}",
            $this->category->value ?? 'all',
            $this->tag ?? '-',
            $this->minLikes ?? 0,
            $this->hasVideo ? 'video' : '-',
        ]);
    }

    /**
     * Viewer-independent lists worth caching (specs/21 §3): the trending and new orders by Town Hall
     * and category. Tag, likes and video filters are rare combinations and run live.
     */
    public function cacheable(): bool
    {
        return in_array($this->sort, [FeedSort::Trending, FeedSort::Newest], true)
            && $this->tag === null && $this->minLikes === null && ! $this->hasVideo;
    }
}
