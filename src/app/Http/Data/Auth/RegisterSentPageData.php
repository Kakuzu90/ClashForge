<?php

namespace App\Http\Data\Auth;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Props for Auth/RegisterSent, the one page every registration ends on.
 */
#[TypeScript]
class RegisterSentPageData extends Data
{
    public function __construct(
        public int $linkMinutes,
    ) {}
}
