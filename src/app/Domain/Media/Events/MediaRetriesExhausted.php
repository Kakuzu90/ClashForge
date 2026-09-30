<?php

namespace App\Domain\Media\Events;

use App\Domain\Media\Enums\MediaCollection;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * The last processing attempt failed with `processing_error`; media:retry-failed will not try
 * again, so the owner has to re-upload (specs/10 §9). Notifications listen from P1-07.
 */
final class MediaRetriesExhausted
{
    use Dispatchable;

    public function __construct(
        public readonly string $mediaUlid,
        public readonly int $userId,
        public readonly MediaCollection $collection,
    ) {}
}
