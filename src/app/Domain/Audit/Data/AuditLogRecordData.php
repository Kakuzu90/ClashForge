<?php

namespace App\Domain\Audit\Data;

use App\Domain\Audit\Enums\AuditAction;
use App\Domain\Audit\Enums\AuditSubject;
use Carbon\CarbonImmutable;

/**
 * One audit entry as the viewer reads it, with usernames joined in. Carries no IP hash. A null
 * username is an account that no longer exists, or no account at all for a console actor.
 */
final readonly class AuditLogRecordData
{
    /**
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>|null  $after
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        public int $id,
        public AuditAction $action,
        public ?int $actorId,
        public ?string $actorUsername,
        public ?string $actorRole,
        public AuditSubject $subject,
        public int $subjectId,
        public ?string $subjectUsername,
        public ?array $before,
        public ?array $after,
        public array $context,
        public ?string $userAgent,
        public ?string $requestId,
        public CarbonImmutable $createdAt,
    ) {}
}
