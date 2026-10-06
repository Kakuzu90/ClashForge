<?php

namespace App\Domain\Operations\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One failed job in the System Health job list (P2-19): its uuid, queue and when it failed. Never
 * the payload or the exception, which can hold personal data.
 */
#[TypeScript]
class FailedJobRowData extends Data
{
    public function __construct(
        public string $uuid,
        public string $queue,
        /** ISO 8601 */
        public string $failedAt,
    ) {}
}
