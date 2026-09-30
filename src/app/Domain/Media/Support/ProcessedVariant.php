<?php

namespace App\Domain\Media\Support;

use App\Domain\Media\Enums\VariantName;

final readonly class ProcessedVariant
{
    public function __construct(
        public VariantName $name,
        public string $contents,
        public string $mimeType,
        public string $extension,
        public int $width,
        public int $height,
    ) {}
}
