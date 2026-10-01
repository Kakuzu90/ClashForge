<?php

namespace App\Domain\Audit\Data;

use App\Domain\Audit\Enums\AuditAction;
use Carbon\CarbonImmutable;

/**
 * Audit log viewer filters (FR-ADMIN-4). Usernames match exactly, ignoring case; `from` and `to`
 * are whole UTC days, both included.
 */
final readonly class AuditLogFilterData
{
    public function __construct(
        public ?string $actor = null,
        public ?string $target = null,
        public ?AuditAction $action = null,
        public ?CarbonImmutable $from = null,
        public ?CarbonImmutable $to = null,
    ) {}
}
