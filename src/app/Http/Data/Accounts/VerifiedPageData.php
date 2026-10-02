<?php

namespace App\Http\Data\Accounts;

use App\Domain\PlayerAccounts\Data\OwnCocAccountData;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Props for Accounts/Verified (step 3). `firstAccount` only on the visit right after the user's
 * first verification, so the reward toast shows once (specs/18 §4).
 */
#[TypeScript]
class VerifiedPageData extends Data
{
    public function __construct(
        public OwnCocAccountData $account,
        public bool $firstAccount,
        public string $profileUsername,
    ) {}
}
