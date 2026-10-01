<?php

namespace App\Domain\Users\Models;

use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Factories\Users\UserStatsFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Denormalised counters for the profile page (specs/07 `user_stats`, specs/08 §5). Written by the
 * Bases listeners and the nightly recompute (P3-04), never from a form. Internal to Users.
 *
 * @property int $user_id
 * @property int $bases_published
 * @property int $total_base_likes
 * @property int $total_base_copies
 * @property int $total_base_views
 * @property int $comments_posted
 * @property CarbonImmutable|null $recomputed_at
 */
#[UseFactory(UserStatsFactory::class)]
class UserStats extends Model
{
    /** @use HasFactory<UserStatsFactory> */
    use HasFactory;

    protected $table = 'user_stats';

    protected $primaryKey = 'user_id';

    public $incrementing = false;

    public $timestamps = false;

    /**
     * Counters are never mass assigned (specs/11 "Mass assignment").
     *
     * @var list<string>
     */
    protected $fillable = [];

    /**
     * @var array<string, int>
     */
    protected $attributes = [
        'bases_published' => 0,
        'total_base_likes' => 0,
        'total_base_copies' => 0,
        'total_base_views' => 0,
        'comments_posted' => 0,
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected function casts(): array
    {
        return [
            'bases_published' => 'integer',
            'total_base_likes' => 'integer',
            'total_base_copies' => 'integer',
            'total_base_views' => 'integer',
            'comments_posted' => 'integer',
            'recomputed_at' => 'immutable_datetime',
        ];
    }
}
