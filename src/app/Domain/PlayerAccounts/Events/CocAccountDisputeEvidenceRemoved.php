<?php

namespace App\Domain\PlayerAccounts\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Staff removed an evidence image that showed an identity document (specs/13 §9, P2-25).
 */
final class CocAccountDisputeEvidenceRemoved implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public readonly int $disputeId,
        /** Who uploaded it */
        public readonly int $userId,
        public readonly string $mediaUlid,
    ) {}
}
