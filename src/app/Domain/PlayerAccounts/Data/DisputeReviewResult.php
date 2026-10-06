<?php

namespace App\Domain\PlayerAccounts\Data;

use App\Models\User;

/**
 * The review page's data, plus the dispute's id and both parties' accounts for what other modules
 * add to the page (its audit trail, Moderation's sanction history). Not a prop itself: only `data`
 * is sent to the page.
 */
final readonly class DisputeReviewResult
{
    public function __construct(
        public DisputeReviewData $data,
        public int $disputeId,
        public User $claimant,
        public ?User $holder,
    ) {}
}
