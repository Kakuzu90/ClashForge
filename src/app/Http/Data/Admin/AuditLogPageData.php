<?php

namespace App\Http\Data\Admin;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Props for Admin/AuditLog. The entries are the deferred `log` prop, so the table shows its row
 * skeleton while they load.
 */
#[TypeScript]
class AuditLogPageData extends Data
{
    /**
     * @param  list<FilterOptionData>  $actions
     */
    public function __construct(
        public AuditLogFiltersData $filters,
        public array $actions,
    ) {}
}
