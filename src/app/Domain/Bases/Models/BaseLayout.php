<?php

namespace App\Domain\Bases\Models;

use App\Domain\Bases\Enums\BaseCategory;
use App\Domain\Bases\Enums\BaseModerationState;
use App\Domain\Bases\Enums\BaseStatus;
use App\Domain\Bases\Enums\BaseVisibility;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Factories\Bases\BaseLayoutFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A published base layout (specs/07 `base_layouts`). Written only by Bases services.
 *
 * @property int $id
 * @property string $ulid
 * @property string $slug
 * @property int $user_id
 * @property int|null $coc_account_id
 * @property string $title
 * @property string|null $description
 * @property int $th_level
 * @property BaseCategory $category
 * @property string $base_link
 * @property string $layout_hash
 * @property BaseVisibility $visibility
 * @property BaseStatus $status
 * @property bool $has_video
 * @property CarbonImmutable|null $published_at
 * @property BaseModerationState $moderation_state
 * @property string|null $flagged_reason
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 * @property CarbonImmutable|null $deleted_at
 */
#[UseFactory(BaseLayoutFactory::class)]
class BaseLayout extends Model
{
    /** @use HasFactory<BaseLayoutFactory> */
    use HasFactory, HasUlids, SoftDeletes;

    /**
     * The author's own fields. Ownership, the link's hash, status and moderation columns are set
     * with forceFill by the services (specs/11 "Mass assignment").
     *
     * @var list<string>
     */
    protected $fillable = [
        'title',
        'description',
        'th_level',
        'category',
        'visibility',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'th_level' => 'integer',
            'category' => BaseCategory::class,
            'visibility' => BaseVisibility::class,
            'status' => BaseStatus::class,
            'has_video' => 'boolean',
            'published_at' => 'immutable_datetime',
            'moderation_state' => BaseModerationState::class,
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
            'deleted_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return list<string>
     */
    public function uniqueIds(): array
    {
        return ['ulid'];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasOne<BaseMetric, $this>
     */
    public function metrics(): HasOne
    {
        return $this->hasOne(BaseMetric::class);
    }

    /**
     * @return BelongsToMany<BaseTag, $this>
     */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(BaseTag::class, 'base_layout_tag');
    }
}
