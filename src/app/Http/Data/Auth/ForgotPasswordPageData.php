<?php

namespace App\Http\Data\Auth;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Props for Auth/ForgotPassword: the generic sent status, if any, and the Turnstile site key.
 */
#[TypeScript]
class ForgotPasswordPageData extends Data
{
    public function __construct(
        public ?string $status,
        public ?string $turnstileSiteKey,
    ) {}
}
