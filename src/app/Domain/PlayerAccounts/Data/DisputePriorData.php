<?php

namespace App\Domain\PlayerAccounts\Data;

use App\Domain\PlayerAccounts\Enums\DisputeStatus;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Another dispute a party was in, with their side and the outcome (specs/13 §5 step 4).
 */
#[TypeScript]
class DisputePriorData extends Data
{
    public function __construct(
        public string $ulid,
        public string $tag,
        /** `claimant` or `holder` */
        public string $side,
        public DisputeStatus $status,
        public string $statusLabel,
        /** ISO 8601 */
        public string $openedAt,
        public bool $reviewable,
    ) {}
}
