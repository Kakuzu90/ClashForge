<?php

namespace App\Domain\PlayerAccounts\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One attempt on the tag from `coc_account_claims`, for the review page (specs/13 §5 step 4).
 */
#[TypeScript]
class DisputeClaimData extends Data
{
    public function __construct(
        public string $username,
        public string $methodLabel,
        public string $statusLabel,
        public ?string $failureLabel,
        /** ISO 8601 */
        public string $at,
    ) {}
}
