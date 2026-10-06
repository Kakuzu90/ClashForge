<?php

namespace App\Domain\PlayerAccounts\Data;

use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * A user's CoC accounts as their profile shows them to one viewer (FR-PROFILE-5, P2-22): every
 * card is one `CocAccountPolicy::view` allows that viewer. `featured` is the hero card, null when
 * the viewer may not see it. `warStars` adds up the verified and disputed cards and `verified` says
 * there is at least one, so both follow `show_coc_accounts`; null and false when there is none.
 */
#[TypeScript]
class ProfileAccountsData extends Data
{
    /**
     * @param  list<PlayerCardData>  $cards
     */
    public function __construct(
        #[DataCollectionOf(PlayerCardData::class)]
        public array $cards,
        public ?PlayerCardData $featured,
        public ?int $warStars,
        public bool $verified,
    ) {}
}
