<?php

namespace App\Domain\PlayerAccounts\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A tag became verified with its in-game token (specs/13 §3.1). `claimId` is the `succeeded` claim
 * row, so each verification is its own event even for the same row. Consumers: the owner's
 * notification and the start of background sync (P2-09); the clan stub (P2-13) and search arrive
 * with their tasks.
 */
final class CocAccountVerified implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public readonly int $accountId,
        public readonly int $userId,
        public readonly int $claimId,
    ) {}
}
