<?php

namespace App\Domain\PlayerAccounts\Events;

use App\Domain\PlayerAccounts\Enums\DisputeStatus;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A dispute ended, by decision, release, token or withdrawal (specs/13 §5). `closedBy` is
 * claimant, holder, admin, token or sweep; `by` narrows it (`holder_token`, `claimant_token`,
 * `other_token`, `holder_release`), so the notices can tell each ending apart (P2-18).
 */
final class CocAccountDisputeClosed implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public readonly int $disputeId,
        public readonly DisputeStatus $status,
        public readonly string $closedBy,
        public readonly ?string $by = null,
    ) {}
}
