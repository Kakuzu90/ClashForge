<?php

namespace App\Domain\PlayerAccounts\Data;

use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * The account page `/accounts/{ulid}` without its grids (specs/18 §6). `notFound` is the stale
 * state after `coc.sync.not_found_stale` 404s in a row (specs/09 §6). `indexable` is for the page
 * meta only.
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
        public int $deltaDays,
        public bool $notFound,
        public bool $isOwn,
        public bool $canVerify,
        public bool $indexable,
    ) {}
}
