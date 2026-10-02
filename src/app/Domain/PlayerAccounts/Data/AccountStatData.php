<?php

namespace App\Domain\PlayerAccounts\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * A stat block on the account page. `delta` compares with the newest snapshot at least
 * `coc.display.delta_days` old; null when there is none, or when the value is unknown.
 */
#[TypeScript]
class AccountStatData extends Data
{
    public function __construct(
        public string $key,
        public string $label,
        public ?int $value,
        public ?int $delta,
    ) {}
}
