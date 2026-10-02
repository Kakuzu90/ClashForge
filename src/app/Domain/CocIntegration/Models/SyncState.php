<?php

namespace App\Domain\CocIntegration\Models;

use App\Domain\CocIntegration\Enums\SyncResourceType;
use App\Domain\CocIntegration\Enums\SyncTier;
use Carbon\CarbonImmutable;
use Database\Factories\CocIntegration\SyncStateFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Sync bookkeeping for one resource (specs/07 `sync_states`), so a restarted scheduler resumes
 * where it stopped. A null `next_due_at` is not scheduled.
 *
 * @property int $id
 * @property SyncResourceType $resource_type
 * @property int $resource_id
 * @property CarbonImmutable|null $last_attempt_at
 * @property CarbonImmutable|null $last_success_at
 * @property int $consecutive_failures
 * @property int $frozen_attempts
 * @property CarbonImmutable|null $next_due_at
 * @property SyncTier $tier
 */
#[UseFactory(SyncStateFactory::class)]
class SyncState extends Model
{
    /** @use HasFactory<SyncStateFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'resource_type',
        'resource_id',
        'last_attempt_at',
        'last_success_at',
        'consecutive_failures',
        'frozen_attempts',
        'next_due_at',
        'tier',
    ];

    protected function casts(): array
    {
        return [
            'resource_type' => SyncResourceType::class,
            'resource_id' => 'integer',
            'last_attempt_at' => 'immutable_datetime',
            'last_success_at' => 'immutable_datetime',
            'consecutive_failures' => 'integer',
            'frozen_attempts' => 'integer',
            'next_due_at' => 'immutable_datetime',
            'tier' => SyncTier::class,
        ];
    }
}
