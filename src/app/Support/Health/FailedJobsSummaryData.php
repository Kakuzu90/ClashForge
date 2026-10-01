<?php

namespace App\Support\Health;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Failed jobs for the admin dashboard (FR-ADMIN-5). Counts and class names only: payloads and
 * exception text can hold personal data and never leave the server.
 */
#[TypeScript]
class FailedJobsSummaryData extends Data
{
    /**
     * @param  list<FailedJobClassData>  $topClasses
     */
    public function __construct(
        public int $lastHour,
        public int $last24Hours,
        public int $alertPerHour,
        public bool $overThreshold,
        public array $topClasses,
    ) {}
}
