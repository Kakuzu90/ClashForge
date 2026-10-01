<?php

namespace App\Domain\Audit\Models;

use App\Domain\Audit\Enums\AuditAction;
use App\Domain\Audit\Enums\AuditSubject;
use App\Domain\Audit\Exceptions\AuditLogIsImmutable;
use Carbon\CarbonImmutable;
use Database\Factories\Audit\AuditLogFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * One privileged or ownership-changing action (specs/07 `audit_logs`). Written only through
 * AuditLogger; an update or delete throws here and is rejected by a database trigger as well.
 *
 * @property int $id
 * @property int|null $actor_id
 * @property string|null $actor_role
 * @property AuditAction $action
 * @property AuditSubject $auditable_type
 * @property int $auditable_id
 * @property array<string, mixed>|null $before
 * @property array<string, mixed>|null $after
 * @property array<string, mixed> $context
 * @property string|null $ip_hash
 * @property string|null $user_agent
 * @property string|null $request_id
 * @property CarbonImmutable $created_at
 */
#[UseFactory(AuditLogFactory::class)]
class AuditLog extends Model
{
    /** @use HasFactory<AuditLogFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'actor_id',
        'actor_role',
        'action',
        'auditable_type',
        'auditable_id',
        'before',
        'after',
        'context',
        'ip_hash',
        'user_agent',
        'request_id',
    ];

    /**
     * @var array<string, string>
     */
    protected $attributes = [
        'context' => '{}',
    ];

    protected static function booted(): void
    {
        static::updating(fn () => throw new AuditLogIsImmutable('Audit entries cannot be edited.'));
        static::deleting(fn () => throw new AuditLogIsImmutable('Audit entries cannot be deleted.'));
    }

    protected function casts(): array
    {
        return [
            'action' => AuditAction::class,
            'auditable_type' => AuditSubject::class,
            'auditable_id' => 'integer',
            'before' => 'array',
            'after' => 'array',
            'context' => 'array',
            'created_at' => 'immutable_datetime',
        ];
    }
}
