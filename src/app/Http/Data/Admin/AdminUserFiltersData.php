<?php

namespace App\Http\Data\Admin;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * The user list filters as applied, to refill the filter bar.
 */
#[TypeScript]
class AdminUserFiltersData extends Data
{
    public function __construct(
        public ?string $search,
        public ?string $role,
        public ?string $status,
    ) {}
}
