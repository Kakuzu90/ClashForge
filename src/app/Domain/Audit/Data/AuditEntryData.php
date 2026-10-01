<?php

namespace App\Domain\Audit\Data;

use App\Domain\Audit\Enums\AuditAction;
use App\Domain\Audit\Enums\AuditSubject;

/**
 * One privileged action to record: who, what, on which record, and the state before and after
 * (NFR-SEC-6). `context` holds the why, such as a reason or the command that ran.
 *
 * Everything here is shown to admins in the audit log viewer as recorded. Never put an IP
 * address, a token, a password or other secrets in `before`, `after` or `context` (specs/11 §5).
 */
final readonly class AuditEntryData
{
    /**
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>|null  $after
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        public AuditActorData $actor,
        public AuditAction $action,
        public AuditSubject $subject,
        public int $subjectId,
        public ?array $before = null,
        public ?array $after = null,
        public array $context = [],
    ) {}
}
