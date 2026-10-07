<?php

namespace App\Domain\Search\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * A filter read from the search text (specs/17 §3), shown as a removable chip. `match` is the text
 * it came from, so removing the chip removes those words from the search.
 */
#[TypeScript]
class ParsedFilterData extends Data
{
    public function __construct(
        public string $key,
        public string $value,
        public string $label,
        public string $match,
    ) {}
}
