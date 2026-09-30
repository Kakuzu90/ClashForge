<?php

namespace App\Domain\Media\Models;

use App\Domain\Media\Enums\MediaCollection;
use App\Domain\Media\Enums\MediaFailureReason;
use App\Domain\Media\Enums\MediaKind;
use App\Domain\Media\Enums\MediaStatus;
use App\Domain\Media\Enums\MediaVisibility;
use Carbon\CarbonImmutable;
use Database\Factories\Media\MediaFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * One row per uploaded file (specs/07 "Media"). Internal to the Media module.
 *
 * @property int $id
 * @property string $ulid
 * @property int $user_id
 * @property string|null $attachable_type
 * @property int|null $attachable_id
 * @property MediaCollection $collection
 * @property MediaKind $kind
 * @property string $disk
 * @property string $path
 * @property string $original_filename
 * @property string|null $mime_type
 * @property string|null $extension
 * @property int $size_bytes
 * @property int|null $width
 * @property int|null $height
 * @property string|null $checksum_sha256
 * @property MediaStatus $status
 * @property MediaFailureReason|null $failure_reason
 * @property MediaVisibility $visibility
 * @property int $position
 * @property int $processing_attempts
 * @property CarbonImmutable|null $processed_at
 * @property CarbonImmutable|null $expires_at
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 * @property CarbonImmutable|null $deleted_at
 */
#[UseFactory(MediaFactory::class)]
class Media extends Model
{
    /** @use HasFactory<MediaFactory> */
    use HasFactory, HasUlids, SoftDeletes;

    protected $table = 'media';

    /**
     * Status, path and processing results are set by the pipeline, never mass-assigned.
     *
     * @var list<string>
     */
    protected $fillable = [
        'collection',
        'original_filename',
        'size_bytes',
        'position',
    ];

    /**
     * The ULID is the public identifier; the bigint id stays internal.
     *
     * @return list<string>
     */
    public function uniqueIds(): array
    {
        return ['ulid'];
    }

    public function getRouteKeyName(): string
    {
        return 'ulid';
    }

    /**
     * @return HasMany<MediaVariant, $this>
     */
    public function variants(): HasMany
    {
        return $this->hasMany(MediaVariant::class);
    }

    /**
     * @param  Builder<Media>  $query
     */
    public function scopeOwnedBy(Builder $query, int|string $userId): void
    {
        $query->where('user_id', $userId);
    }

    protected function casts(): array
    {
        return [
            'collection' => MediaCollection::class,
            'kind' => MediaKind::class,
            'status' => MediaStatus::class,
            'failure_reason' => MediaFailureReason::class,
            'visibility' => MediaVisibility::class,
            'size_bytes' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
            'position' => 'integer',
            'processing_attempts' => 'integer',
            'processed_at' => 'immutable_datetime',
            'expires_at' => 'immutable_datetime',
        ];
    }
}
