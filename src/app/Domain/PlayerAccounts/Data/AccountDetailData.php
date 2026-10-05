<?php

namespace App\Domain\PlayerAccounts\Data;

use App\Domain\GameAssets\Data\GameAssetData;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * The account page `/accounts/{ulid}` without its grids (specs/18 §6). `builderHall` heads the
 * Builder Base tab, as the Town Hall on the card heads the Home Village one; `builderLeague*` is that
 * tab's ranked data, as `card.league*` is the Home Village's. `notFound` is the stale
 * state after `coc.sync.not_found_stale` 404s in a row (specs/09 §6). `canDetach` and `canFeature`
 * are the owner's actions (P2-14); `canFeature` is false once it is featured. `indexable` is for the
 * page meta only.
 */
#[TypeScript]
class AccountDetailData extends Data
{
    /**
     * @param  list<AccountStatData>  $stats
     */
    public function __construct(
        public PlayerCardData $card,
        #[DataCollectionOf(AccountStatData::class)]
        public array $stats,
        public ?string $builderLeagueName,
        public ?GameAssetData $builderLeague,
        public ?GameAssetData $builderHall,
        public int $deltaDays,
        public bool $notFound,
        public bool $isOwn,
        public bool $canVerify,
        public bool $canDetach,
        public bool $canFeature,
        public bool $indexable,
    ) {}
}
