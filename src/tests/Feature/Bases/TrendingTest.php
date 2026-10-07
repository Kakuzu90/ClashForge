<?php

use App\Domain\Bases\Enums\BaseStatus;
use App\Domain\Bases\Models\BaseLayout;
use App\Domain\Bases\Models\BaseMetric;
use App\Domain\Bases\Services\TrendingService;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Date;

// P3-03: `bases:recompute-trending` (FR-BASE-12; specs/17 §5; specs/20 §2, §3).

beforeEach(fn () => Date::setTestNow('2026-10-07 12:00:00'));

function trendingScore(BaseLayout $base): float
{
    return (float) BaseMetric::query()->whereKey($base->id)->value('trending_score');
}

it('scores recent bases from their counters, and only recent ones without --all', function () {
    $days = (int) config('bases.trending.active_days');
    $recent = BaseLayout::factory()->withMetrics(['likes_count' => 10, 'copies_count' => 4, 'comments_count' => 2, 'views_count' => 100])
        ->create(['published_at' => Date::now()->subHours(10)]);
    $old = BaseLayout::factory()->withMetrics(['likes_count' => 10, 'trending_score' => 9.0])->create(['published_at' => Date::now()->subDays($days + 1)]);
    $draft = BaseLayout::factory()->processing()->withMetrics(['likes_count' => 50])->create();

    $this->artisan('bases:recompute-trending')->expectsOutput('Scored 1 bases.')->assertSuccessful();

    expect(trendingScore($recent))->toEqualWithDelta(TrendingService::score(10, 4, 2, 100, Date::now()->subHours(10), Date::now()), 1e-5)
        ->and(trendingScore($old))->toBe(9.0)
        ->and(trendingScore($draft))->toBe(0.0)
        ->and(BaseMetric::query()->whereKey($recent->id)->value('score_updated_at'))->not->toBeNull();

    $this->artisan('bases:recompute-trending --all')->expectsOutput('Scored 2 bases.')->assertSuccessful();
    expect(trendingScore($old))->toBeLessThan(9.0)->toBeGreaterThan(0.0);
});

it('follows the specs/17 §5 formula with the configured weights', function () {
    $now = Date::now();
    config(['bases.trending' => [...config('bases.trending'), 'like' => 2.0, 'copy' => 3.0, 'comment' => 1.5, 'view' => 0.1, 'offset_hours' => 2, 'exponent' => 1.5]]);

    // (2·10 + 3·4 + 1.5·2 + 0.1·100) / (10 + 2)^1.5 = 45 / 41.569…
    expect(TrendingService::score(10, 4, 2, 100, $now->subHours(10), $now))->toEqualWithDelta(45 / (12 ** 1.5), 1e-9)
        // Fresh bases rank by points; a day decays them; copies outweigh likes.
        ->and(TrendingService::score(0, 1, 0, 0, $now, $now))->toBeGreaterThan(TrendingService::score(1, 0, 0, 0, $now, $now))
        ->and(TrendingService::score(5, 0, 0, 0, $now->subDay(), $now))->toBeLessThan(TrendingService::score(5, 0, 0, 0, $now, $now))
        ->and(TrendingService::score(0, 0, 0, 0, $now, $now))->toBe(0.0);

    config(['bases.trending.copy' => 10.0]);
    expect(TrendingService::score(0, 1, 0, 0, $now, $now))->toEqualWithDelta(10 / (2 ** 1.5), 1e-9);
});

it('keeps the full score for the first base of a layout only (specs/23 §3)', function () {
    $counts = ['likes_count' => 10];
    $original = BaseLayout::factory()->forLayout('TH16:WB:SAME')->withMetrics($counts)->create(['published_at' => Date::now()->subHours(5)]);
    $copy = BaseLayout::factory()->forLayout('TH16:WB:SAME')->withMetrics($counts)->create(['published_at' => Date::now()->subHours(5)]);
    $alone = BaseLayout::factory()->withMetrics($counts)->create(['published_at' => Date::now()->subHours(5)]);

    app(TrendingService::class)->recompute();

    expect(trendingScore($original))->toEqualWithDelta(trendingScore($alone), 1e-6)
        ->and(trendingScore($copy))->toEqualWithDelta(trendingScore($alone) * (float) config('bases.trending.duplicate_penalty'), 1e-6);
});

it('ignores an unpublished earlier copy when choosing the original', function () {
    BaseLayout::factory()->forLayout('TH16:WB:X')->withMetrics()->create(['status' => BaseStatus::Removed, 'published_at' => Date::now()->subDay()]);
    $live = BaseLayout::factory()->forLayout('TH16:WB:X')->withMetrics(['likes_count' => 4])->create(['published_at' => Date::now()->subHour()]);

    app(TrendingService::class)->recompute();

    expect(trendingScore($live))->toEqualWithDelta(TrendingService::score(4, 0, 0, 0, Date::now()->subHour(), Date::now()), 1e-5);
});

it('scores in batches of the configured size', function () {
    config(['bases.trending.batch_size' => 2]);
    BaseLayout::factory()->count(5)->withMetrics(['likes_count' => 1])->create(['published_at' => Date::now()->subHour()]);

    expect(app(TrendingService::class)->recompute())->toBe(5)
        ->and(BaseMetric::query()->where('trending_score', '>', 0)->count())->toBe(5);
});

it('is scheduled every 15 minutes off the hour and nightly in full (specs/20 §3)', function () {
    $events = collect(app(Schedule::class)->events())->filter(fn ($e) => str_contains((string) $e->command, 'bases:recompute-trending'));

    expect($events->map(fn ($e) => $e->expression)->values()->all())->toEqualCanonicalizing(['11-59/15 * * * *', '15 3 * * *'])
        ->and($events->first(fn ($e) => str_contains((string) $e->command, '--all')))->not->toBeNull();
});
