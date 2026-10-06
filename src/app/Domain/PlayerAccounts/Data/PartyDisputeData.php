<?php

namespace App\Domain\PlayerAccounts\Data;

use App\Domain\PlayerAccounts\Enums\DisputeParty;
use App\Domain\PlayerAccounts\Enums\DisputeStatus;
use App\Domain\PlayerAccounts\Enums\PartyDisputeOutcome;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * A dispute as one of its parties sees it (P2-16, owner decision 2026-10-06): where it stands, whose
 * turn it is and until when, what the viewer may do, and only what the viewer sent. Never the other
 * party, their statements or their evidence (specs/13 §5 guardrails).
 *
 * `waitingOn` is `holder`, `claimant` or `admin` while it runs. `accountUlid` is the viewer's own
 * row of the tag, when they hold one: the token link for a holder, the account for a winner.
 * `withdrawCountsTowardBar` warns a claimant that withdrawing now counts like a denial.
 */
#[TypeScript]
class PartyDisputeData extends Data
{
    /**
     * @param  list<PartySubmissionData>  $submissions
     */
    public function __construct(
        public string $ulid,
        public string $tag,
        public DisputeParty $role,
        public DisputeStatus $status,
        public string $statusLabel,
        public ?string $waitingOn,
        /** ISO 8601: when the wait on the viewer ends (holder: escalation; claimant: withdrawal) */
        public ?string $deadline,
        /** ISO 8601 */
        public string $openedAt,
        /** ISO 8601 */
        public ?string $closedAt,
        public ?PartyDisputeOutcome $outcome,
        public ?string $outcomeLabel,
        public ?string $accountUlid,
        #[DataCollectionOf(PartySubmissionData::class)]
        public array $submissions,
        public int $evidenceLeft,
        public bool $canRespond,
        public bool $canWithdraw,
        public bool $canRelease,
        public bool $canVerify,
        public bool $withdrawCountsTowardBar,
    ) {}
}
