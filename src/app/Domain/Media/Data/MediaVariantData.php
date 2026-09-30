<?php

namespace App\Domain\Media\Data;

use App\Domain\Media\Enums\VariantName;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One rendition with intrinsic dimensions, so every <img> reserves its space (NFR-PERF-5).
 */
#[TypeScript]
class MediaVariantData extends Data
{
    public function __construct(
        public VariantName $name,
        public string $url,
        public int $width,
        public int $height,
    ) {}
}
