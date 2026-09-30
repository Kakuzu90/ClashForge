<?php

namespace App\Console\Commands\Platform;

use App\Domain\Auth\Enums\Role;
use App\Domain\Auth\Services\RoleAssignmentService;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * The only way to grant or remove `super_admin` (specs/04 §1). Safe to run twice.
 */
class AssignRoleCommand extends Command
{
    protected $signature = 'platform:assign-role {username} {role : user, moderator, admin or super_admin}';

    protected $description = 'Set the role of an account; ends its sessions when the role changes';

    public function handle(RoleAssignmentService $roles): int
    {
        $role = Role::tryFrom((string) $this->argument('role'));

        if ($role === null) {
            $this->components->error('Unknown role. Use one of: '.implode(', ', Role::values()).'.');

            return self::INVALID;
        }

        $user = User::query()->where('username', (string) $this->argument('username'))->first();

        if ($user === null) {
            $this->components->error('No account with that username.');

            return self::FAILURE;
        }

        $changed = $roles->assign($user, $role, actor: 'console');

        $this->components->info($changed ? "Role set to {$role->value}; the account's sessions were ended." : "Already {$role->value}; nothing changed.");
        Log::info('platform.assign_role', ['user' => $user->ulid, 'role' => $role->value, 'changed' => $changed]);

        return self::SUCCESS;
    }
}
