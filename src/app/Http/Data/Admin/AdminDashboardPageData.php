<?php

namespace App\Http\Data\Admin;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Props for Admin/Dashboard. With `platformStats`, the deferred `signups`, `failedJobs` and
 * `storage` panels follow, each in its own request so one failure leaves the others standing.
 */
#[TypeScript]
class AdminDashboardPageData extends Data
{
    public function __construct(
        public bool $platformStats,
    ) {}
}
