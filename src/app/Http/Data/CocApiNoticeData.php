<?php

namespace App\Http\Data;

use App\Domain\CocIntegration\Enums\CocCircuitReason;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * The site banner while the Clash of Clans API is unavailable (specs/09 §7). Only the reason: no
 * time, since the next probe and an announced end look the same; no key or error detail.
 */
#[TypeScript]
class CocApiNoticeData extends Data
{
    public function __construct(
        public CocCircuitReason $reason,
    ) {}
}
