<?php

namespace App\Domain\PlayerAccounts\Models;

use App\Domain\PlayerAccounts\Enums\DisputeStatus;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Factories\PlayerAccounts\CocAccountDisputeFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A contested tag (specs/13 §5, specs/07).
 *
 * @property int $id
 * @property string $ulid
 * @property int $coc_account_id
 * @property string $tag_normalized
 * @property int $claimant_id
 * @property int|null $current_holder_id
 * @property string $reason
 * @property list<array{party: string, note: string|null, media: list<string>, at: string}> $evidence
 * @property DisputeStatus $status
 * @property int|null $assigned_admin_id
 * @property string|null $decision_note
 * @property int|null $decided_by
 * @property CarbonImmutable|null $decided_at
 * @property string|null $closed_by
 * @property CarbonImmutable $awaiting_since
 * @property CarbonImmutable|null $escalated_at
 * @property int $holder_reminders_sent
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 */
#[UseFactory(CocAccountDisputeFactory::class)]
class CocAccountDispute extends Model
{
    /** @use HasFactory<CocAccountDisputeFactory> */
    use HasFactory, HasUlids;

    /**
     * Nothing: every column is set by DisputeService with forceFill (specs/11 "Mass assignment").
     *
     * @var list<string>
     */
    protected $fillable = [];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'evidence' => '[]',
    ];

    /**
     * @return list<string>
     */
    public function uniqueIds(): array
    {
        return ['ulid'];
    }

    /**
     * @return BelongsTo<CocAccount, $this>
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(CocAccount::class, 'coc_account_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function claimant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'claimant_id')->withTrashed();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function holder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'current_holder_id')->withTrashed();
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->whereIn('status', DisputeStatus::ACTIVE);
    }

    protected function casts(): array
    {
        return [
            'status' => DisputeStatus::class,
            'evidence' => 'array',
            'decided_at' => 'immutable_datetime',
            'awaiting_since' => 'immutable_datetime',
            'escalated_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
