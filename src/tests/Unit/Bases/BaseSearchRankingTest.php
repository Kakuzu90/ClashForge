<?php

use App\Domain\Bases\Data\FeedFiltersData;
use App\Domain\Bases\Queries\BaseSearchRanking;
use App\Domain\Search\Data\SourceQuery;
use Carbon\CarbonImmutable;
use Tests\TestCase;

// specs/17 §5: the "Best match" blend for bases (P3-05).

uses(TestCase::class);

function rankingQuery(string $term): SourceQuery
{
    return new SourceQuery($term, new FeedFiltersData, null, 20, true, 'sig', CarbonImmutable::parse('2026-10-07T12:00:00+00:00'));
}

it('blends text rank, trending and recency with the configured weights', function () {
    [$sql, $bindings] = BaseSearchRanking::score(rankingQuery('ring'));
    $weights = config('platform.search.ranking');

    expect($sql)->toContain('ts_rank_cd(base_layouts.search_vector,')
        ->toContain(', 32)')
        ->toContain('base_metrics.trending_score / (base_metrics.trending_score +')
        ->toContain('power(0.5,')
        ->and($bindings)->toBe([
            (float) $weights['text'], 'ring', 'ring',
            (float) $weights['trending'], (float) $weights['trending_pivot'],
            (float) $weights['recency'], '2026-10-07T12:00:00+00:00', (float) $weights['recency_half_life_days'],
        ]);
});

it('leaves the text term out when only filters were searched', function () {
    [$sql, $bindings] = BaseSearchRanking::score(rankingQuery(''));

    expect($sql)->not->toContain('ts_rank_cd')->and($bindings)->toHaveCount(5);
});

it('reads new weights from config', function () {
    config(['platform.search.ranking.trending' => 0.9]);

    expect(BaseSearchRanking::score(rankingQuery(''))[1][0])->toBe(0.9);
});
