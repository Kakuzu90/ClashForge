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
 * are the owner's actions (P2-14); `canFeature` is false once it is featured. `disputeUlid` is the
 * running dispute over the owner's own `disputed` row, for their link to it (P2-16). `canRefresh` is
 * the owner's manual refresh and `refreshWaitSeconds` its cooldown, 0 when free (P2-20).
 * `images` are the custom images (FR-COC-11): ready ones for everyone, plus the owner's processing
 * and failed ones; `canManageImages` and `imagesMax` drive the owner's upload area (P2-23).
 * `indexable` is for the page meta only.
 */
#[TypeScript]
class AccountDetailData extends Data
{
    /**
     * @param  list<AccountStatData>  $stats
     * @param  list<AccountImageData>  $images
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
        public bool $canRefresh,
        public int $refreshWaitSeconds,
        public bool $indexable,
        #[DataCollectionOf(AccountImageData::class)]
        public array $images = [],
        public bool $canManageImages = false,
        public int $imagesMax = 0,
        public ?string $disputeUlid = null,
    ) {}
}
