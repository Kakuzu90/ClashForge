<?php

namespace App\Http\Data\Auth;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Props for Auth/ResetPassword: the token and email from the link, sent back with the form.
 */
#[TypeScript]
class ResetPasswordPageData extends Data
{
    public function __construct(
        public string $token,
        public string $email,
    ) {}
}
