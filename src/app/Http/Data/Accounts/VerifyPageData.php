<?php

namespace App\Http\Data\Accounts;

use App\Domain\PlayerAccounts\Data\OwnCocAccountData;
use App\Domain\PlayerAccounts\Data\VerifyResultData;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Props for Accounts/Verify (step 2): the unverified account and the answer to the last token.
 */
#[TypeScript]
class VerifyPageData extends Data
{
    public function __construct(
        public OwnCocAccountData $account,
        public ?VerifyResultData $result,
    ) {}
}
