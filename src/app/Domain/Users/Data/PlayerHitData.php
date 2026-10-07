<?php

namespace App\Domain\Users\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * A player in search results (FR-SEARCH-1): only profiles the viewer may open, so the display name
 * and avatar can show. `bio` is cut to `platform.search.snippet_length`.
 */
#[TypeScript]
class PlayerHitData extends Data
{
    public function __construct(
        public string $username,
        public ?string $displayName,
        public ?string $avatarUrl,
        public ?string $bio,
    ) {}
}
