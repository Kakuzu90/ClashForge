<?php

namespace App\Domain\Users\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * An ISO code with its English name (country, language).
 */
#[TypeScript]
class CodeLabelData extends Data
{
    public function __construct(
        public string $code,
        public string $label,
    ) {}
}
