<?php

namespace App\Domain\Media\Data;

use App\Domain\Media\Enums\MediaStatus;

/**
 * One media item attached to a parent, for the module that owns the parent (specs/05 §2). `variants`
 * is keyed by variant name and only filled once the media is `ready`.
 */
final readonly class AttachedMediaData
{
    /**
     * @param  array<string, MediaVariantData>  $variants
     */
    public function __construct(
        public string $ulid,
        public MediaStatus $status,
        public array $variants,
    ) {}
}
