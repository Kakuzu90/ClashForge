<?php

namespace App\Domain\Auth\Data;

use App\Domain\Auth\Enums\Role;
use App\Domain\Auth\Enums\UserStatus;

/**
 * Admin user list filters (FR-ADMIN-2). `search` with an `@` matches an email exactly, otherwise
 * a username prefix, both ignoring case. `status` is the effective status (specs/23 §7).
 */
final readonly class AdminUserFilterData
{
    public function __construct(
        public ?string $search = null,
        public ?Role $role = null,
        public ?UserStatus $status = null,
    ) {}
}
