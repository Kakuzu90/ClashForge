<?php

namespace App\Domain\Bases\Data;

use App\Domain\Bases\Enums\BaseStatus;

/**
 * A base just published: `Published`, or `Processing` while its media finish (FR-BASE-5).
 */
final readonly class PublishedBaseData
{
    public function __construct(
        public string $ulid,
        public string $slug,
        public BaseStatus $status,
    ) {}
}
