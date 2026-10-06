<?php

namespace App\Domain\PlayerAccounts\Events;

use App\Domain\PlayerAccounts\Enums\DisputeParty;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * An admin asked one party of a dispute for more (specs/13 §5 step 4).
 */
final class CocAccountDisputeInfoRequested implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public readonly int $disputeId,
        public readonly DisputeParty $party,
        /** Unix time the new wait began, for the email's event key (P2-18) */
        public readonly int $awaitingSince,
    ) {}
}
