<?php

namespace App\Domain\Media\Support;

/**
 * What a MediaProcessor learned about the original and the renditions it produced.
 */
final readonly class ProcessedMedia
{
    /**
     * @param  list<ProcessedVariant>  $variants
     */
    public function __construct(
        public string $mimeType,
        public string $extension,
        public int $width,
        public int $height,
        public array $variants,
        public ?float $durationSeconds = null,
    ) {}
}
