<?php

namespace App\Http\Data\Admin;

use App\Domain\Audit\Data\AuditLogRecordData;
use App\Domain\Auth\Enums\Role;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One entry of the audit trail on the admin user detail: what changed, who acted and when. Leaner
 * than AuditLogEntryData: no context, user agent or request id, which the trail does not show
 * (the full entry stays one click away in the audit log).
 */
#[TypeScript]
class AuditTrailEntryData extends Data
{
    /**
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>|null  $after
     */
    public function __construct(
        public int $id,
        public string $actionLabel,
        public ?string $actorUsername,
        public ?string $actorRoleLabel,
        public ?string $actorVia,
        public ?array $before,
        public ?array $after,
        /** ISO 8601 */
        public string $createdAt,
    ) {}

    public static function fromRecord(AuditLogRecordData $record): self
    {
        $via = $record->context['via'] ?? null;

        return new self(
            id: $record->id,
            actionLabel: $record->action->label(),
            actorUsername: $record->actorUsername,
            actorRoleLabel: $record->actorRole === null ? null : (Role::tryFrom($record->actorRole)?->label() ?? $record->actorRole),
            actorVia: $record->actorId === null && is_string($via) ? $via : null,
            before: $record->before,
            after: $record->after,
            createdAt: $record->createdAt->toIso8601String(),
        );
    }
}
