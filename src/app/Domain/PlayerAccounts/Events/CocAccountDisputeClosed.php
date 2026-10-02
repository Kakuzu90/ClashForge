<?php

namespace App\Domain\PlayerAccounts\Events;

use App\Domain\PlayerAccounts\Enums\DisputeStatus;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A dispute ended, by decision, release, token or withdrawal (specs/13 §5). Both parties hear
 * about it with P2-18.
 */
final class CocAccountDisputeClosed implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public readonly int $disputeId,
        public readonly DisputeStatus $status,
    ) {}
}
