<?php

namespace App\Domain\Auth\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * The admin user detail (specs/12 §4 author context, FR-ADMIN-6: support from data). No password,
 * remember token, 2FA or IP data. Status is the effective one: a passed suspension reads active.
 */
#[TypeScript]
class AdminUserDetailData extends Data
{
    public function __construct(
        public string $ulid,
        public string $username,
        public string $email,
        /** ISO 8601, null while unverified */
        public ?string $emailVerifiedAt,
        public string $roleLabel,
        public string $statusLabel,
        public string $statusTone,
        /** The reason the account holder is shown, while a sanction applies */
        public ?string $statusReason,
        /** ISO 8601, null for no end date */
        public ?string $statusEndsAt,
        /** ISO 8601 */
        public string $joinedAt,
        /** ISO 8601 */
        public ?string $lastSignInAt,
        public int $activeSessions,
        /** ISO 8601, set once the account is soft deleted */
        public ?string $deletedAt,
    ) {}
}
