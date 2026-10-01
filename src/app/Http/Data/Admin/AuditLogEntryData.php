<?php

namespace App\Http\Data\Admin;

use App\Domain\Audit\Data\AuditLogRecordData;
use App\Domain\Auth\Enums\Role;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One row of the audit log viewer. No IP data (specs/11 "Data exposure via page props"). `actor`
 * is a username, or how a non-account actor ran (`console`); `subjectName` is null once the
 * account no longer exists.
 */
#[TypeScript]
class AuditLogEntryData extends Data
{
    /**
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>|null  $after
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        public int $id,
        public string $action,
        public string $actionLabel,
        public ?string $actorUsername,
        public ?string $actorRoleLabel,
        public ?string $actorVia,
        public string $subjectLabel,
        public ?string $subjectName,
        public ?array $before,
        public ?array $after,
        public array $context,
        public ?string $userAgent,
        public ?string $requestId,
        /** ISO 8601 */
        public string $createdAt,
    ) {}

    public static function fromRecord(AuditLogRecordData $record): self
    {
        $via = $record->context['via'] ?? null;

        return new self(
            id: $record->id,
            action: $record->action->value,
            actionLabel: $record->action->label(),
            actorUsername: $record->actorUsername,
            actorRoleLabel: $record->actorRole === null ? null : (Role::tryFrom($record->actorRole)?->label() ?? $record->actorRole),
            actorVia: $record->actorId === null && is_string($via) ? $via : null,
            subjectLabel: $record->subject->label(),
            subjectName: $record->subjectUsername,
            before: $record->before,
            after: $record->after,
            context: $record->context,
            userAgent: $record->userAgent,
            requestId: $record->requestId,
            createdAt: $record->createdAt->toIso8601String(),
        );
    }
}
