<?php

namespace App\Domain\PlayerAccounts\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * An evidence image: null URLs while it is not ready, or has no rendition to show.
 */
#[TypeScript]
class DisputeEvidenceImageData extends Data
{
    public function __construct(
        public string $ulid,
        public ?string $url,
        public ?string $thumbUrl,
    ) {}
}
