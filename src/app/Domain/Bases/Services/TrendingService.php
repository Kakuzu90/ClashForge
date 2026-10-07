<?php

namespace App\Domain\Bases\Services;

use App\Domain\Bases\Enums\BaseStatus;
use App\Domain\Bases\Models\BaseLayout;
use App\Domain\Bases\Models\BaseMetric;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;

/**
 * Recomputes `base_metrics.trending_score` on a schedule, never live (FR-BASE-12, specs/17 §5):
 *
 *   (like·likes + copy·copies + comment·comments + view·views) / (hours since publish + offset)^exponent
 *
 * from the counter columns, with weights in `bases.trending`. A later copy of a layout another
 * author published first keeps `duplicate_penalty` of its score, so one popular layout does not
 * fill the feed (specs/23 §3). The specs/17 §5 anti-gaming weights need the interaction rows and
 * join with P3-04.
 */
class TrendingService
{
    public function __construct(private readonly CacheInvalidator $caches) {}

    /**
     * Every 15 minutes the bases published in the last `active_days`; with `$all`, every published
     * base (the nightly pass, so older scores keep decaying). Returns how many were scored.
     */
    public function recompute(bool $all = false): int
    {
        $now = Date::now();
        $scored = 0;

        BaseLayout::query()
            ->where('status', BaseStatus::Published)
            ->whereNotNull('published_at')
            ->when(! $all, fn ($query) => $query->where('published_at', '>=', $now->subDays((int) config('bases.trending.active_days'))))
            ->select(['id', 'layout_hash', 'published_at'])
            ->chunkById((int) config('bases.trending.batch_size'), function ($bases) use ($now, &$scored): void {
                $first = $this->firstOfLayout(array_values(array_unique(array_map(fn (BaseLayout $base): string => $base->layout_hash, $bases->all()))));
                $metrics = BaseMetric::query()->whereIn('base_layout_id', $bases->modelKeys())->get()->keyBy('base_layout_id');

                $rows = [];
                foreach ($bases as $base) {
                    /** @var BaseLayout $base */
                    $metric = $metrics->get($base->id);

                    if ($metric === null || $base->published_at === null) {
                        continue;
                    }

                    $score = self::score($metric->likes_count, $metric->copies_count, $metric->comments_count, $metric->views_count, $base->published_at, $now);
                    if (($first[$base->layout_hash] ?? $base->id) !== $base->id) {
                        $score *= (float) config('bases.trending.duplicate_penalty');
                    }

                    $rows[] = ['base_layout_id' => $base->id, 'trending_score' => $score, 'score_updated_at' => $now];
                }

                BaseMetric::query()->upsert($rows, ['base_layout_id'], ['trending_score', 'score_updated_at']);
                $scored += count($rows);
            });

        $this->caches->baseFeeds();

        return $scored;
    }

    public static function score(int $likes, int $copies, int $comments, int $views, CarbonImmutable $publishedAt, CarbonImmutable $now): float
    {
        $weight = fn (string $key): float => (float) config("bases.trending.{$key}");
        $points = $weight('like') * $likes + $weight('copy') * $copies + $weight('comment') * $comments + $weight('view') * $views;
        $hours = max(0.0, $publishedAt->diffInSeconds($now, false) / 3600);

        return $points / (($hours + $weight('offset_hours')) ** $weight('exponent'));
    }

    /**
     * The earliest published base of each layout (ties: the lower id), whoever published it.
     *
     * @param  list<string>  $hashes
     * @return array<string, int> layout hash → base id
     */
    private function firstOfLayout(array $hashes): array
    {
        $first = [];

        BaseLayout::query()->where('status', BaseStatus::Published)->whereNotNull('published_at')->whereIn('layout_hash', $hashes)
            ->orderBy('published_at')->orderBy('id')
            ->get(['id', 'layout_hash'])
            ->each(function (BaseLayout $base) use (&$first): void {
                $first[$base->layout_hash] ??= $base->id;
            });

        return $first;
    }
}
