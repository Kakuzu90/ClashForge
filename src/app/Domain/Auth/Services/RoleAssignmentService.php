<?php

namespace App\Domain\Auth\Services;

use App\Domain\Auth\Enums\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * The only way a role changes (specs/11 "Broken authorization"). The role is an enum case, never
 * a raw request value. A privilege change ends the account's sessions and cycles the remember
 * token, so its next session starts from a fresh sign-in (specs/04 §4).
 *
 * The 2FA precondition for staff roles arrives with 2FA (Phase 2) and the audit_logs entry with
 * AuditLogger (P1-06); until then the `security` log entry is the record.
 */
class RoleAssignmentService
{
    public function assign(User $target, Role $role, string $actor): bool
    {
        $previous = $target->role;

        if ($previous === $role) {
            return false;
        }

        DB::transaction(function () use ($target, $role): void {
            $target->forceFill(['role' => $role, 'remember_token' => Str::random(60)])->save();

            DB::table((string) config('session.table', 'sessions'))->where('user_id', $target->id)->delete();
        });

        Log::channel('security')->warning('auth.role_changed', [
            'user' => $target->ulid,
            'from' => $previous->value,
            'to' => $role->value,
            'actor' => $actor,
        ]);

        return true;
    }
}
