<?php

namespace App\Http\Data\Admin;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * The filters as applied, to refill the filter bar. Dates are `YYYY-MM-DD` in UTC.
 */
#[TypeScript]
class AuditLogFiltersData extends Data
{
    public function __construct(
        public ?string $actor,
        public ?string $target,
        public ?string $action,
        public ?string $from,
        public ?string $to,
    ) {}
}
