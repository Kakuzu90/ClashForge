<?php

namespace App\Domain\PlayerAccounts\Data;

use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * A page of the admin dispute queue with its cursors (P2-17).
 */
#[TypeScript]
class DisputeQueueData extends Data
{
    /**
     * @param  list<DisputeQueueRowData>  $entries
     */
    public function __construct(
        #[DataCollectionOf(DisputeQueueRowData::class)]
        public array $entries,
        public ?string $nextCursor,
        public ?string $previousCursor,
    ) {}
}
