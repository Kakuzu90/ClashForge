<?php

namespace App\Domain\PlayerAccounts\Data;

use App\Domain\PlayerAccounts\Enums\CocAccountStatus;
use App\Domain\PlayerAccounts\Enums\DisputeStatus;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * The admin review page of one dispute (specs/13 §5 step 4, specs/12 §4: one screen with
 * everything). `blockedReason` says why this admin cannot decide at all (closed, or a party they
 * do not outrank); each option says why it is unavailable.
 */
#[TypeScript]
class DisputeReviewData extends Data
{
    /**
     * @param  list<DisputeEvidenceData>  $evidence
     * @param  list<DisputeClaimData>  $claims
     * @param  list<DisputeSnapshotData>  $snapshots
     * @param  list<DisputeDecisionOptionData>  $decisions
     */
    public function __construct(
        public string $ulid,
        public string $tag,
        public DisputeStatus $status,
        public string $statusLabel,
        public bool $active,
        /** ISO 8601 */
        public string $openedAt,
        /** ISO 8601 */
        public string $waitingSince,
        public ?string $escalatedAt,
        public ?string $decidedAt,
        public ?string $decidedBy,
        public ?string $assignedTo,
        public ?string $decisionNote,
        public string $accountName,
        public CocAccountStatus $accountStatus,
        public ?int $accountTownHall,
        public DisputePartyData $claimant,
        public ?DisputePartyData $holder,
        #[DataCollectionOf(DisputeEvidenceData::class)]
        public array $evidence,
        #[DataCollectionOf(DisputeClaimData::class)]
        public array $claims,
        #[DataCollectionOf(DisputeSnapshotData::class)]
        public array $snapshots,
        #[DataCollectionOf(DisputeDecisionOptionData::class)]
        public array $decisions,
        public ?string $blockedReason,
        public int $noteMax,
        /** The tag this dispute suspended can be released (P2-25); `releaseBlockedReason` says why this admin cannot. */
        public bool $canReleaseTag = false,
        public ?string $releaseBlockedReason = null,
    ) {}
}
