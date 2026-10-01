<?php

namespace App\Domain\Moderation\Models;

use App\Domain\Moderation\Enums\ReasonCode;
use App\Domain\Moderation\Enums\SanctionType;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Factories\Moderation\UserSanctionFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Date;

/**
 * A sanction on an account, active or past (specs/07 `user_sanctions`). Active means not lifted
 * and not past `expires_at`. Only `lifted_by` / `lifted_at` change after insert.
 *
 * @property int $id
 * @property int $user_id
 * @property SanctionType $type
 * @property ReasonCode $reason_code
 * @property string $public_reason
 * @property string $internal_note
 * @property int $issued_by
 * @property CarbonImmutable $starts_at
 * @property CarbonImmutable|null $expires_at
 * @property int|null $lifted_by
 * @property CarbonImmutable|null $lifted_at
 * @property int $moderation_action_id
 * @property CarbonImmutable $created_at
 */
#[UseFactory(UserSanctionFactory::class)]
class UserSanction extends Model
{
    /** @use HasFactory<UserSanctionFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    /**
     * Who lifted it and when are set by SanctionService only.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'type',
        'reason_code',
        'public_reason',
        'internal_note',
        'issued_by',
        'starts_at',
        'expires_at',
        'moderation_action_id',
    ];

    /**
     * @param  Builder<UserSanction>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->whereNull('lifted_at')
            ->where(fn (Builder $q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', Date::now()));
    }

    public function isActive(): bool
    {
        return $this->lifted_at === null && ($this->expires_at === null || $this->expires_at->greaterThan(Date::now()));
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by')->withTrashed();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function lifter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'lifted_by')->withTrashed();
    }

    protected function casts(): array
    {
        return [
            'type' => SanctionType::class,
            'reason_code' => ReasonCode::class,
            'starts_at' => 'immutable_datetime',
            'expires_at' => 'immutable_datetime',
            'lifted_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
        ];
    }
}
