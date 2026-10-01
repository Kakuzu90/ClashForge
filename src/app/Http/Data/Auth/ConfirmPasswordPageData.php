<?php

namespace App\Http\Data\Auth;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Props for Auth/ConfirmPassword: how long a confirmation lasts, for the copy.
 */
#[TypeScript]
class ConfirmPasswordPageData extends Data
{
    public function __construct(
        public int $minutes,
    ) {}
}
