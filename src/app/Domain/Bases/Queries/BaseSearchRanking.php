<?php

namespace App\Domain\Bases\Queries;

use App\Domain\Search\Data\SourceQuery;
use App\Domain\Search\Services\TextQuery;

/**
 * "Best match" for bases (specs/17 §5), as one SQL expression with its bindings: text rank and
 * trending squashed into 0..1 as x / (x + pivot) (`ts_rank_cd` normalisation 32 for the rank),
 * and recency halving every `recency_half_life_days` from the time the list was ranked at. Weights
 * are `platform.search.ranking`. Author quality reads 0 until P3-04 keeps its counters; the
 * duplicate penalty is already inside the trending score.
 */
final class BaseSearchRanking
{
    /**
     * @return array{string, list<string|float>}
     */
    public static function score(SourceQuery $query): array
    {
        $weights = (array) config('platform.search.ranking');
        $parts = [];
        $bindings = [];

        if ($query->hasTerm()) {
            $parts[] = 'CAST(? AS double precision) * ts_rank_cd(base_layouts.search_vector, '.TextQuery::ANY.', 32)';
            array_push($bindings, (float) $weights['text'], ...TextQuery::bindings($query->term));
        }

        $parts[] = 'CAST(? AS double precision) * (base_metrics.trending_score / (base_metrics.trending_score + CAST(? AS double precision)))';
        array_push($bindings, (float) $weights['trending'], (float) $weights['trending_pivot']);

        $parts[] = 'CAST(? AS double precision) * power(0.5, greatest(extract(epoch from (CAST(? AS timestamptz) - base_layouts.published_at)), 0) / 86400.0 / CAST(? AS double precision))';
        array_push($bindings, (float) $weights['recency'], $query->now->toIso8601String(), (float) $weights['recency_half_life_days']);

        return ['('.implode(' + ', $parts).')', $bindings];
    }
}
