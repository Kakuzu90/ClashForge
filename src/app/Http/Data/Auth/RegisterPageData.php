<?php

namespace App\Http\Data\Auth;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Props for Auth/Register: the field limits for hints, the Turnstile site key and the encrypted
 * time the form was shown (the minimum fill time check, specs/11).
 */
#[TypeScript]
class RegisterPageData extends Data
{
    public function __construct(
        public int $usernameMin,
        public int $usernameMax,
        public int $passwordMin,
        public ?string $turnstileSiteKey,
        public string $formStarted,
    ) {}
}
