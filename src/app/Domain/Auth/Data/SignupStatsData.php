<?php

namespace App\Domain\Auth\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * New sign-ups for the admin dashboard (FR-ADMIN-5), counted back from now.
 */
#[TypeScript]
class SignupStatsData extends Data
{
    public function __construct(
        public SignupCountData $last24Hours,
        public SignupCountData $last7Days,
        public SignupCountData $last30Days,
    ) {}
}
