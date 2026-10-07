<?php

namespace App\Domain\Bases\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One page of a feed. `nextCursor` is null on the last page, and on the last page an anonymous
 * visitor may load (`bases.feed.max_pages`).
 */
#[TypeScript]
class FeedPageData extends Data
{
    /**
     * @param  list<BaseCardData>  $cards
     */
    public function __construct(
        public array $cards,
        public ?string $nextCursor,
        public int $page,
    ) {}
}
