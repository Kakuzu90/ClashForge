<?php

namespace App\Domain\CocIntegration\Data;

use Carbon\CarbonImmutable;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One API key on the System Health page (specs/09 §3, specs/20 §6), by id only: the first
 * characters of the token's sha256, never the token. `reason` is the API's own reason code.
 */
#[TypeScript]
class CocKeyData extends Data
{
    public function __construct(
        public string $id,
        public bool $healthy,
        public ?string $reason,
        public ?CarbonImmutable $unhealthySince,
    ) {}
}
