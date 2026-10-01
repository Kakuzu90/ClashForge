<?php

namespace App\Domain\Moderation\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One row of an account's sanction history on the admin user detail (specs/12 §4: prior
 * sanctions). `state` is `active`, `lifted` or `ended` (ran out).
 */
#[TypeScript]
class SanctionData extends Data
{
    public function __construct(
        public string $typeLabel,
        public string $reasonLabel,
        public string $publicReason,
        public string $internalNote,
        public ?string $issuedBy,
        /** ISO 8601 */
        public string $startsAt,
        /** ISO 8601, null for no end date */
        public ?string $endsAt,
        public string $state,
        public string $stateLabel,
        public ?string $liftedBy,
        /** ISO 8601 */
        public ?string $liftedAt,
        public ?string $liftNote,
    ) {}
}
