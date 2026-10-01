<?php

namespace App\Domain\Moderation\Data;

use App\Domain\Moderation\Enums\ReasonCode;

/**
 * What an admin enters to suspend or ban (FR-ADMIN-3). `publicReason` is shown to the account
 * holder; `internalNote` stays with staff. `days` is for suspensions only.
 */
final readonly class ApplySanctionData
{
    public function __construct(
        public ReasonCode $reasonCode,
        public string $publicReason,
        public string $internalNote,
        public ?int $days = null,
    ) {}
}
