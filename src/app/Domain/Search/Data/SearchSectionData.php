<?php

namespace App\Domain\Search\Data;

use App\Domain\Bases\Data\BaseCardData;
use App\Domain\PlayerAccounts\Data\PlayerCardData;
use App\Domain\Search\Enums\SearchType;
use App\Domain\Users\Data\PlayerHitData;
use Spatie\LaravelData\Data;

/**
 * One kind of result (specs/17 §1): bases as feed cards, players or accounts. `hasMore` drives a
 * grouped search's "See all"; `nextCursor` a single kind's "Load more".
 */
class SearchSectionData extends Data
{
    /**
     * @param  list<BaseCardData>|list<PlayerHitData>|list<PlayerCardData>  $items
     */
    public function __construct(
        public SearchType $type,
        public array $items,
        public bool $hasMore,
        public ?string $nextCursor,
        public ?SearchFacetsData $facets = null,
    ) {}
}
