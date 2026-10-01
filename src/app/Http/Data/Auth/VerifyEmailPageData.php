<?php

namespace App\Http\Data\Auth;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Props for Auth/VerifyEmail: the signed-in account's own address and the last resend status.
 */
#[TypeScript]
class VerifyEmailPageData extends Data
{
    public function __construct(
        public string $email,
        public ?string $status,
        public int $linkMinutes,
    ) {}
}
