<?php

namespace App\Domain\Operations\Data;

use Spatie\LaravelData\Data;

/**
 * What a retry or delete did (P2-19): jobs handled, jobs another admin had already handled, jobs
 * that could not be retried (their payload no longer loads) and jobs of the target still failed,
 * for example past the per-action cap.
 */
class FailedJobActionResultData extends Data
{
    public function __construct(
        public int $done,
        public int $skipped,
        public int $kept,
        public int $left,
    ) {}
}
