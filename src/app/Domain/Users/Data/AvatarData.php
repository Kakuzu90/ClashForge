<?php

namespace App\Domain\Users\Data;

use App\Domain\Media\Enums\MediaStatus;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * A profile's avatar: its processing state and the square renditions once ready (specs/10 §5:
 * 512 `full`, 128 `card`, 48 `thumb`). No URLs until processing has finished.
 */
#[TypeScript]
class AvatarData extends Data
{
    public function __construct(
        public ?MediaStatus $status,
        public ?string $url512,
        public ?string $url128,
        public ?string $url48,
    ) {}
}
