<?php

namespace App\Domain\PlayerAccounts\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * The CoC account a base credits, as a base card shows it (P3-03).
 */
#[TypeScript]
class CreditedAccountData extends Data
{
    public function __construct(
        public string $ulid,
        public string $name,
        public ?int $thLevel,
    ) {}
}
