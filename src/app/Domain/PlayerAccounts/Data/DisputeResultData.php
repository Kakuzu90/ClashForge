<?php

namespace App\Domain\PlayerAccounts\Data;

use App\Domain\PlayerAccounts\Enums\DisputeRefusal;
use App\Domain\PlayerAccounts\Enums\DisputeStatus;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * What a dispute action answered: the dispute and its new status, or why it was refused.
 */
#[TypeScript]
class DisputeResultData extends Data
{
    public function __construct(
        public ?string $disputeUlid,
        public ?DisputeStatus $status,
        public ?DisputeRefusal $refusal = null,
    ) {}

    public static function refused(DisputeRefusal $refusal, ?string $disputeUlid = null): self
    {
        return new self($disputeUlid, null, $refusal);
    }
}
