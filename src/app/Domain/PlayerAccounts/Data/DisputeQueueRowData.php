<?php

namespace App\Domain\PlayerAccounts\Data;

use App\Domain\PlayerAccounts\Enums\DisputeStatus;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One row of the admin dispute queue (P2-17). No statement, evidence or internal note: those stay
 * on the review page.
 */
#[TypeScript]
class DisputeQueueRowData extends Data
{
    public function __construct(
        public string $ulid,
        public string $tag,
        public string $claimant,
        public ?string $holder,
        public DisputeStatus $status,
        public string $statusLabel,
        /** ISO 8601: when the current wait began */
        public string $waitingSince,
        /** ISO 8601 */
        public string $openedAt,
        public ?string $assignedTo,
        public bool $needsSuperAdmin,
    ) {}
}
