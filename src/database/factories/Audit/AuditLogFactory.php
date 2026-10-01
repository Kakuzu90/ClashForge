<?php

namespace Database\Factories\Audit;

use App\Domain\Audit\Enums\AuditAction;
use App\Domain\Audit\Enums\AuditSubject;
use App\Domain\Audit\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AuditLog>
 */
class AuditLogFactory extends Factory
{
    protected $model = AuditLog::class;

    /**
     * A console role change on a fresh account, as `platform:assign-role` writes it.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'actor_id' => null,
            'actor_role' => null,
            'action' => AuditAction::RoleChanged,
            'auditable_type' => AuditSubject::User,
            'auditable_id' => User::factory(),
            'before' => ['role' => 'user'],
            'after' => ['role' => 'moderator'],
            'context' => ['via' => 'console'],
            'ip_hash' => null,
            'user_agent' => null,
            'request_id' => null,
        ];
    }

    /**
     * Acted from the web by a signed-in staff member.
     */
    public function by(User $actor): static
    {
        return $this->state([
            'actor_id' => $actor->id,
            'actor_role' => $actor->role->value,
            'context' => [],
            'ip_hash' => str_repeat('a', 64),
            'user_agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 14_0) Firefox/131.0',
            'request_id' => strtolower((string) str()->ulid()),
        ]);
    }

    public function on(User $target): static
    {
        return $this->state(['auditable_type' => AuditSubject::User, 'auditable_id' => $target->id]);
    }
}
