<?php

namespace App\Domain\Bases\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One choice in a feed control (a category or a sort), with its label.
 */
#[TypeScript]
class FeedOptionData extends Data
{
    public function __construct(
        public string $value,
        public string $label,
    ) {}
}
