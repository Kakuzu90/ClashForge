<?php

namespace App\Domain\Media\Support;

use App\Domain\Media\Enums\VariantName;

/**
 * One rendition, held as bytes (images) or as a file in the job's temp dir (a transcoded video),
 * so a large output is streamed to storage instead of read into memory.
 */
final readonly class ProcessedVariant
{
    public function __construct(
        public VariantName $name,
        public string $contents,
        public string $mimeType,
        public string $extension,
        public int $width,
        public int $height,
        public ?string $localPath = null,
    ) {}

    public static function fromFile(VariantName $name, string $localPath, string $mimeType, string $extension, int $width, int $height): self
    {
        return new self($name, '', $mimeType, $extension, $width, $height, $localPath);
    }

    public function sizeBytes(): int
    {
        return $this->localPath === null ? strlen($this->contents) : (int) filesize($this->localPath);
    }
}
