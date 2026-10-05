<?php

namespace App\Domain\PlayerAccounts\Data;

use App\Domain\PlayerAccounts\Enums\DisputeDecision;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * A decision the form offers, and why it is not available now. The service decides again on submit.
 */
#[TypeScript]
class DisputeDecisionOptionData extends Data
{
    public function __construct(
        public DisputeDecision $value,
        public string $label,
        public bool $available,
        public ?string $unavailableReason,
    ) {}
}
