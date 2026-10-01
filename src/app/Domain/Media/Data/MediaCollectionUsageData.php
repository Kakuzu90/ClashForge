<?php

namespace App\Domain\Media\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Storage held by one collection's live media: originals plus every variant.
 */
#[TypeScript]
class MediaCollectionUsageData extends Data
{
    public function __construct(
        public string $collection,
        public string $label,
        public int $bytes,
        public int $objects,
    ) {}
}
