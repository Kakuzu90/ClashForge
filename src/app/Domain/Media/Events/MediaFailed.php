<?php

namespace App\Domain\Media\Events;

use App\Domain\Media\Enums\MediaCollection;
use App\Domain\Media\Enums\MediaFailureReason;
use App\Domain\Media\Enums\MediaStatus;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Processing ended without variants: `failed`, or `quarantined` when the file looked hostile.
 */
final class MediaFailed
{
    use Dispatchable;

    public function __construct(
        public readonly string $mediaUlid,
        public readonly int $userId,
        public readonly MediaCollection $collection,
        public readonly MediaStatus $status,
        public readonly MediaFailureReason $reason,
    ) {}
}
