<?php

namespace App\Http\Data\Admin;

use App\Domain\Moderation\Data\SanctionData;
use App\Domain\PlayerAccounts\Data\DisputeReviewData;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Props for Admin/Disputes/Show: the review, each party's prior sanctions (specs/12 §4) and the
 * dispute's own audit trail, evidence views included.
 */
#[TypeScript]
class AdminDisputeShowPageData extends Data
{
    /**
     * @param  list<SanctionData>  $claimantSanctions
     * @param  list<SanctionData>  $holderSanctions
     * @param  list<AuditTrailEntryData>  $auditTrail
     */
    public function __construct(
        public DisputeReviewData $dispute,
        #[DataCollectionOf(SanctionData::class)]
        public array $claimantSanctions,
        #[DataCollectionOf(SanctionData::class)]
        public array $holderSanctions,
        #[DataCollectionOf(AuditTrailEntryData::class)]
        public array $auditTrail,
        public bool $moreAuditEntries,
    ) {}
}
