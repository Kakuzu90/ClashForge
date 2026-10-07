<?php

namespace App\Domain\Bases\Models;

use Carbon\CarbonImmutable;
use Database\Factories\Bases\BaseMetricFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A base's counters (specs/07 `base_metrics`), apart from `base_layouts` so counter writes never
 * lock the row every feed query reads. Created with the base; the counters arrive with P3-03/P3-04.
 *
 * @property int $base_layout_id
 * @property int $likes_count
 * @property int $comments_count
 * @property int $bookmarks_count
 * @property int $views_count
 * @property int $copies_count
 * @property int $reports_count
 * @property float $trending_score
 * @property CarbonImmutable|null $score_updated_at
 */
#[UseFactory(BaseMetricFactory::class)]
class BaseMetric extends Model
{
    /** @use HasFactory<BaseMetricFactory> */
    use HasFactory;

    public $timestamps = false;

    public $incrementing = false;

    protected $primaryKey = 'base_layout_id';

    /**
     * Counters change only through Bases services.
     *
     * @var list<string>
     */
    protected $fillable = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'likes_count' => 'integer',
            'comments_count' => 'integer',
            'bookmarks_count' => 'integer',
            'views_count' => 'integer',
            'copies_count' => 'integer',
            'reports_count' => 'integer',
            'trending_score' => 'float',
            'score_updated_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return BelongsTo<BaseLayout, $this>
     */
    public function base(): BelongsTo
    {
        return $this->belongsTo(BaseLayout::class, 'base_layout_id');
    }
}
