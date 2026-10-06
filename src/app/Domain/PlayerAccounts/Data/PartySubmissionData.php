<?php

namespace App\Domain\PlayerAccounts\Data;

use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One of the viewer's own submissions in their dispute (P2-16): the opening statement for the
 * claimant, then each answer. Never the other party's.
 */
#[TypeScript]
class PartySubmissionData extends Data
{
    /**
     * @param  list<DisputeEvidenceImageData>  $images
     */
    public function __construct(
        public ?string $note,
        #[DataCollectionOf(DisputeEvidenceImageData::class)]
        public array $images,
        /** ISO 8601 */
        public string $at,
        public bool $opening,
    ) {}
}
