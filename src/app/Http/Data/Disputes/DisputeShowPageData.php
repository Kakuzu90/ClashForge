<?php

namespace App\Http\Data\Disputes;

use App\Domain\Media\Data\UploadCollectionData;
use App\Domain\PlayerAccounts\Data\PartyDisputeData;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Props for Disputes/Show (P2-16): the dispute as the viewing party sees it, and the evidence limits
 * for their answer.
 */
#[TypeScript]
class DisputeShowPageData extends Data
{
    public function __construct(
        public PartyDisputeData $dispute,
        public UploadCollectionData $evidenceUpload,
        public int $textMax,
    ) {}
}
