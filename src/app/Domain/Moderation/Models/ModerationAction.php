<?php

namespace App\Domain\Moderation\Models;

use App\Domain\Moderation\Enums\ModerationActionType;
use App\Domain\Moderation\Enums\ReasonCode;
use App\Domain\Moderation\Exceptions\ModerationActionIsImmutable;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Factories\Moderation\ModerationActionFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * What a staff member did (specs/07 `moderation_actions`). Written by Moderation services only;
 * update and delete throw here and are rejected by a database trigger as well.
 *
 * @property int $id
 * @property int|null $case_id
 * @property int $actor_id
 * @property ModerationActionType $action
 * @property string $target_type
 * @property int $target_id
 * @property int|null $target_user_id
 * @property ReasonCode $reason_code
 * @property string $note
 * @property int|null $duration_hours
 * @property array<string, mixed> $metadata
 * @property string|null $ip_hash
 * @property CarbonImmutable $created_at
 */
#[UseFactory(ModerationActionFactory::class)]
class ModerationAction extends Model
{
    /** @use HasFactory<ModerationActionFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    public const TARGET_USER = 'user';

    public const TARGET_SANCTION = 'user_sanction';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'case_id',
        'actor_id',
        'action',
        'target_type',
        'target_id',
        'target_user_id',
        'reason_code',
        'note',
        'duration_hours',
        'metadata',
        'ip_hash',
    ];

    /**
     * @var array<string, string>
     */
    protected $attributes = [
        'metadata' => '{}',
    ];

    protected static function booted(): void
    {
        static::updating(fn () => throw new ModerationActionIsImmutable('Moderation actions cannot be edited.'));
        static::deleting(fn () => throw new ModerationActionIsImmutable('Moderation actions cannot be deleted.'));
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id')->withTrashed();
    }

    protected function casts(): array
    {
        return [
            'action' => ModerationActionType::class,
            'reason_code' => ReasonCode::class,
            'target_id' => 'integer',
            'duration_hours' => 'integer',
            'metadata' => 'array',
            'created_at' => 'immutable_datetime',
        ];
    }
}
