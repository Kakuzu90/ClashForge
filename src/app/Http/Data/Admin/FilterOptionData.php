<?php

namespace App\Http\Data\Admin;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class FilterOptionData extends Data
{
    public function __construct(
        public string $value,
        public string $label,
    ) {}
}
