<?php

namespace App\Domain\PlayerAccounts\Data;

use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One submission in a dispute, the claimant's opening statement included (`opening`), in the
 * order they arrived. Images carry short-lived signed URLs (specs/10 §8).
 */
#[TypeScript]
class DisputeEvidenceData extends Data
{
    /**
     * @param  list<DisputeEvidenceImageData>  $images
     */
    public function __construct(
        /** `claimant` or `holder` */
        public string $party,
        public ?string $note,
        #[DataCollectionOf(DisputeEvidenceImageData::class)]
        public array $images,
        /** ISO 8601 */
        public string $at,
        public bool $opening,
    ) {}
}
