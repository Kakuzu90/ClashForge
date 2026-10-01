<?php

namespace App\Domain\Moderation\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Which sanction actions the viewer may take on this account right now: the policy (role, rank,
 * active standing) and the account's state together. Show/hide only; the service re-checks.
 */
#[TypeScript]
class SanctionAbilitiesData extends Data
{
    public function __construct(
        public bool $suspend,
        public bool $ban,
        public bool $lift,
        /** `suspension` or `ban` while one is active, for the lift dialog */
        public ?string $activeType,
    ) {}
}
