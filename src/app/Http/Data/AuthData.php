<?php

namespace App\Http\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class AuthData extends Data
{
    /**
     * @param  array<string, bool>  $can  Policy-computed abilities for nav and chrome (specs/04)
     */
    public function __construct(
        public ?AuthUserData $user,
        public array $can,
    ) {}
}
