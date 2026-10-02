<?php

namespace App\Domain\PlayerAccounts\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A claimant opened a dispute against a verified holder (specs/13 §5 step 2). The holder's notice
 * arrives with P2-18.
 */
final class CocAccountDisputeOpened implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public readonly int $disputeId,
        public readonly int $claimantId,
        public readonly ?int $holderId,
    ) {}
}
