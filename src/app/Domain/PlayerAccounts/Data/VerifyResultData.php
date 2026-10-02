<?php

namespace App\Domain\PlayerAccounts\Data;

use App\Domain\PlayerAccounts\Enums\VerifyOutcome;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * What a token verification answered. `accountUlid` is null only when a tag the user had not
 * attached failed before any row existed. `superseded` when it took the tag from another verified
 * holder (specs/13 §3.1); `featured` when it became the user's featured account.
 */
#[TypeScript]
class VerifyResultData extends Data
{
    public function __construct(
        public VerifyOutcome $outcome,
        public ?string $accountUlid,
        public bool $superseded = false,
        public bool $featured = false,
        public ?int $retryAfter = null,
    ) {}
}
