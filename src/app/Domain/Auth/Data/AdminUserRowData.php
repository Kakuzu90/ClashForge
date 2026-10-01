<?php

namespace App\Domain\Auth\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One row of the admin user list. Email is here because only admins see this list (specs/11 §5).
 * Role and status come as labels for display; `statusTone` picks the pill colour.
 */
#[TypeScript]
class AdminUserRowData extends Data
{
    public function __construct(
        public string $ulid,
        public string $username,
        public string $email,
        public bool $emailVerified,
        public string $roleLabel,
        public string $statusLabel,
        public string $statusTone,
        /** ISO 8601 */
        public string $joinedAt,
        /** ISO 8601 */
        public ?string $lastSignInAt,
        public bool $deleted,
    ) {}
}
