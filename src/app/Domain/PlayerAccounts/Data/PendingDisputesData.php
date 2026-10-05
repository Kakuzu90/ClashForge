<?php

namespace App\Domain\PlayerAccounts\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * The dashboard's pending disputes panel (FR-ADMIN-5, P2-17): disputes waiting for an admin, the
 * oldest wait, and `open` disputes past the holder's window that the hourly sweep has not moved yet.
 */
#[TypeScript]
class PendingDisputesData extends Data
{
    public function __construct(
        public int $awaitingAdmin,
        /** ISO 8601, null when none waits */
        public ?string $oldestWaitingSince,
        public int $pastHolderWindow,
        public int $running,
    ) {}
}
