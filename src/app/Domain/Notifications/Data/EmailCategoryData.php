<?php

namespace App\Domain\Notifications\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class EmailCategoryData extends Data
{
    public function __construct(public string $key, public string $label, public bool $enabled, public bool $locked, public ?string $hint = null) {}
}
