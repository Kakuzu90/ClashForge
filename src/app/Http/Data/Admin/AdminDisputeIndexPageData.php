<?php

namespace App\Http\Data\Admin;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Props for Admin/Disputes/Index. The rows are the deferred `disputes` prop.
 */
#[TypeScript]
class AdminDisputeIndexPageData extends Data
{
    /**
     * @param  list<FilterOptionData>  $views
     */
    public function __construct(
        public string $view,
        public bool $mine,
        public array $views,
    ) {}
}
