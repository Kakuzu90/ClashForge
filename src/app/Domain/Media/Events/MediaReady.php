<?php

namespace App\Domain\Media\Events;

use App\Domain\Media\Enums\MediaCollection;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * All variants are stored; parents waiting on this media may publish (specs/05 §2, specs/10 §3).
 */
final class MediaReady
{
    use Dispatchable;

    public function __construct(
        public readonly string $mediaUlid,
        public readonly int $userId,
        public readonly MediaCollection $collection,
    ) {}
}
