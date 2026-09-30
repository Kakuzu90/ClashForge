<?php

namespace App\Domain\Media\Contracts;

use App\Domain\Media\Enums\MediaCollection;
use App\Domain\Media\Exceptions\MediaRejected;
use App\Domain\Media\Support\ProcessedMedia;

/**
 * Validates and re-encodes one downloaded original. Swappable so a hosted transcoder can replace
 * the in-house one without touching the pipeline (specs/10 §6).
 */
interface MediaProcessor
{
    /**
     * @throws MediaRejected when the file fails validation
     */
    public function process(string $localPath, MediaCollection $collection): ProcessedMedia;
}
