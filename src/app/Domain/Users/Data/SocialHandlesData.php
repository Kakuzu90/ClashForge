<?php

namespace App\Domain\Users\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * The stored handles, for the edit form. Handles only, never URLs (specs/07 `profiles.socials`).
 */
#[TypeScript]
class SocialHandlesData extends Data
{
    public function __construct(
        public ?string $youtube,
        public ?string $twitch,
        public ?string $x,
        public ?string $discord,
    ) {}
}
