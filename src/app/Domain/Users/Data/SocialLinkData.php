<?php

namespace App\Domain\Users\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * A social handle on the public profile. `url` is built from a fixed https host (SocialLinks) and
 * is null for networks without public profile pages (Discord).
 */
#[TypeScript]
class SocialLinkData extends Data
{
    public function __construct(
        public string $network,
        public string $label,
        public string $handle,
        public ?string $url,
    ) {}
}
