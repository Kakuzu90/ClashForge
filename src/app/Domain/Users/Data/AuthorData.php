<?php

namespace App\Domain\Users\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Who made a piece of public content, as another module's list shows it (a base card, P3-03).
 */
#[TypeScript]
class AuthorData extends Data
{
    public function __construct(
        public string $username,
        public ?string $displayName,
        public ?string $avatarUrl,
    ) {}
}
