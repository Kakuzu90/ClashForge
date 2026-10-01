<?php

namespace App\Http\Data\Admin;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Props for Admin/Users/Index. The rows are the deferred `users` prop.
 */
#[TypeScript]
class AdminUserIndexPageData extends Data
{
    /**
     * @param  list<FilterOptionData>  $roles
     * @param  list<FilterOptionData>  $statuses
     */
    public function __construct(
        public AdminUserFiltersData $filters,
        public array $roles,
        public array $statuses,
    ) {}
}
