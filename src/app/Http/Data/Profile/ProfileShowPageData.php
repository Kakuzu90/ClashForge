<?php

namespace App\Http\Data\Profile;

use App\Domain\PlayerAccounts\Data\ProfileAccountsData;
use App\Domain\Users\Data\PublicProfileData;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Props for Profile/Show. `accounts` holds only the CoC accounts this viewer may see: the owner's
 * rows in any state but released, others' verified and disputed ones behind `show_coc_accounts`
 * (P2-22).
 */
#[TypeScript]
class ProfileShowPageData extends Data
{
    public function __construct(
        public PublicProfileData $profile,
        public ProfileAccountsData $accounts,
    ) {}
}
