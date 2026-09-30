<?php

namespace App\Domain\Media\Models;

use Carbon\CarbonImmutable;
use Database\Factories\Media\MediaStorageOrphanFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A bucket key the storage reconcile found with no media row (specs/10 §9). It is deleted on the
 * next consecutive detection, never on first sight.
 *
 * @property string $path
 * @property CarbonImmutable $first_seen_at
 * @property CarbonImmutable $last_seen_at
 */
#[UseFactory(MediaStorageOrphanFactory::class)]
class MediaStorageOrphan extends Model
{
    /** @use HasFactory<MediaStorageOrphanFactory> */
    use HasFactory;

    public $timestamps = false;

    public $incrementing = false;

    protected $primaryKey = 'path';

    protected $keyType = 'string';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'path',
        'first_seen_at',
        'last_seen_at',
    ];

    protected function casts(): array
    {
        return [
            'first_seen_at' => 'immutable_datetime',
            'last_seen_at' => 'immutable_datetime',
        ];
    }
}
