<?php

namespace App\Domain\Audit\Data;

use App\Domain\Audit\Enums\AuditAction;
use App\Domain\Audit\Enums\AuditSubject;
use Carbon\CarbonImmutable;

/**
 * Audit log viewer filters (FR-ADMIN-4). Usernames match exactly, ignoring case; `from` and `to`
 * are whole UTC days, both included. `subjectId` narrows to one subject's entries, an account by
 * default (the admin user detail's audit trail) or `subject` (a dispute's, P2-17).
 */
final readonly class AuditLogFilterData
{
    public function __construct(
        public ?string $actor = null,
        public ?string $target = null,
        public ?AuditAction $action = null,
        public ?CarbonImmutable $from = null,
        public ?CarbonImmutable $to = null,
        public ?int $subjectId = null,
        public AuditSubject $subject = AuditSubject::User,
    ) {}
}
