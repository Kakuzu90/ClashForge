<?php

namespace App\Domain\Operations\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * The System Health page's failed jobs (specs/20 §5–6): every failure kept for `retentionDays`,
 * grouped by class, most failures first.
 */
#[TypeScript]
class FailedJobsByClassData extends Data
{
    /**
     * @param  list<FailedJobGroupData>  $classes
     */
    public function __construct(
        public int $total,
        public int $lastHour,
        public int $alertPerHour,
        public bool $overThreshold,
        public int $retentionDays,
        public array $classes,
    ) {}
}
