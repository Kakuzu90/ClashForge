<?php

namespace App\Domain\Auth\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Accounts created inside one window, and how many of them have confirmed their email.
 */
#[TypeScript]
class SignupCountData extends Data
{
    public function __construct(
        public int $total,
        public int $verified,
    ) {}
}
