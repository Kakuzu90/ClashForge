<?php

namespace App\Http\Data\Auth;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Props for Auth/Login: the flash status after a reset, if any.
 */
#[TypeScript]
class LoginPageData extends Data
{
    public function __construct(
        public ?string $status,
    ) {}
}
