<?php

namespace App\Domain\Media\Data;

use App\Domain\Media\Enums\MediaCollection;

/**
 * What the browser declares before uploading. Only a first-pass filter: the worker re-checks
 * everything against the real bytes (specs/10 §3).
 */
final readonly class CreateUploadIntentData
{
    public function __construct(
        public MediaCollection $collection,
        public string $filename,
        public int $sizeBytes,
        public string $mimeType,
    ) {}
}
