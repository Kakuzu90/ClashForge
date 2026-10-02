<?php

namespace App\Http\Data\Profile;

use App\Domain\PlayerAccounts\Data\OwnCocAccountData;
use App\Domain\Users\Data\PublicProfileData;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Props for Profile/Show. `ownAccounts` only on the viewer's own profile: their accounts in any
 * state, until PlayerCards replace the list (P2-04); null for everyone else.
 */
#[TypeScript]
class ProfileShowPageData extends Data
{
    /**
     * @param  list<OwnCocAccountData>|null  $ownAccounts
     */
    public function __construct(
        public PublicProfileData $profile,
        #[DataCollectionOf(OwnCocAccountData::class)]
        public ?array $ownAccounts = null,
    ) {}
}
