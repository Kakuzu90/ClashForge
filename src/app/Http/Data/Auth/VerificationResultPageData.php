<?php

namespace App\Http\Data\Auth;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Props for Auth/VerificationResult. `outcome` is an EmailVerificationOutcome value. While it is
 * `pending`, `username` names the account and `confirmUrl` is where the button posts; `signedIn`
 * picks the next step offered.
 */
#[TypeScript]
class VerificationResultPageData extends Data
{
    public function __construct(
        public string $outcome,
        public string $message,
        public bool $signedIn,
        public ?string $username,
        public ?string $confirmUrl,
    ) {}
}
