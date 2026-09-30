<?php

namespace App\Http\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * The only user fields the client ever receives (specs/11 "Data exposure via page props").
 * No email, IP data, 2FA state or role: Vue decides nothing from role (specs/04).
 */
#[TypeScript]
class AuthUserData extends Data
{
    public function __construct(
        public string $username,
        public ?string $avatarUrl,
        public bool $emailVerified,
    ) {}
}
