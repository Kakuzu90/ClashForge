<?php

namespace App\Domain\PlayerAccounts\Data;

use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One side of a dispute on the review page (specs/13 §5 step 4: both users' history, prior
 * disputes by either party). `deleted` for an anonymised account, whose username is the tombstone.
 */
#[TypeScript]
class DisputePartyData extends Data
{
    /**
     * @param  list<DisputePriorData>  $priorDisputes
     */
    public function __construct(
        public string $ulid,
        public string $username,
        public string $roleLabel,
        public string $statusLabel,
        public string $statusTone,
        /** ISO 8601 */
        public string $joinedAt,
        public int $verifiedAccounts,
        public bool $deleted,
        #[DataCollectionOf(DisputePriorData::class)]
        public array $priorDisputes,
    ) {}
}
