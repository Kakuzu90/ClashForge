<?php

namespace App\Domain\Auth\Services;

use App\Domain\Audit\Data\AuditActorData;
use App\Domain\Audit\Data\AuditEntryData;
use App\Domain\Audit\Enums\AuditAction;
use App\Domain\Audit\Enums\AuditSubject;
use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Auth\Enums\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * The only way a role changes (specs/11 "Broken authorization"). The role is an enum case, never
 * a raw request value. A privilege change ends the account's sessions and cycles the remember
 * token, so its next session starts from a fresh sign-in (specs/04 §4). The `audit_logs` entry
 * commits with the change; the `security` log line feeds the role-change alert (specs/11 §3).
 *
 * The 2FA precondition for staff roles arrives with 2FA (Phase 2).
 */
class RoleAssignmentService
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function assign(User $target, Role $role, AuditActorData $actor): bool
    {
        return DB::transaction(function () use ($target, $role, $actor): bool {
            $target = User::query()->whereKey($target->id)->lockForUpdate()->firstOrFail();
            $previous = $target->role;

            if ($previous === $role) {
                return false;
            }

            $target->forceFill(['role' => $role, 'remember_token' => Str::random(60)])->save();

            DB::table((string) config('session.table', 'sessions'))->where('user_id', $target->id)->delete();

            $this->audit->record(new AuditEntryData(
                actor: $actor,
                action: AuditAction::RoleChanged,
                subject: AuditSubject::User,
                subjectId: $target->id,
                before: ['role' => $previous->value],
                after: ['role' => $role->value],
            ));
            Log::channel('security')->warning('auth.role_changed', [
                'user' => $target->ulid,
                'from' => $previous->value,
                'to' => $role->value,
                'actor' => $actor->via ?? $actor->id,
            ]);

            return true;
        });
    }
}
