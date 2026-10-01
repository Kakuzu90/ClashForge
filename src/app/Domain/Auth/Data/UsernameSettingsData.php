<?php

namespace App\Domain\Auth\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * The username card on Settings/Profile (FR-PROFILE-7). `canChange` is the server's answer;
 * `nextChangeAt` (ISO 8601) is set while the 30-day wait runs, and `needsVerifiedEmail` names the
 * other reason a change is closed.
 */
#[TypeScript]
class UsernameSettingsData extends Data
{
    public function __construct(
        public string $username,
        public bool $canChange,
        public bool $needsVerifiedEmail,
        public ?string $nextChangeAt,
        public int $changeDays,
        public int $reservationDays,
        public int $minLength,
        public int $maxLength,
    ) {}
}
