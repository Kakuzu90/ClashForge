<?php

namespace App\Domain\Clans\Services;

use App\Domain\Clans\Data\ClanSummaryData;
use App\Domain\Clans\Models\Clan;

/**
 * Clan reads for other modules (specs/05 §2). One query for any number of clans.
 */
class ClanReadModel
{
    /**
     * @param  list<int>  $ids
     * @return array<int, ClanSummaryData> by clan id; unknown ids are left out
     */
    public function summaries(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        return Clan::query()
            ->whereIn('id', array_values(array_unique($ids)))
            ->get(['id', 'tag', 'name', 'level', 'badge_urls'])
            ->mapWithKeys(fn (Clan $clan): array => [$clan->id => new ClanSummaryData(
                tag: $clan->tag,
                name: $clan->name,
                level: $clan->level,
                badgeUrls: $clan->badge_urls,
            )])
            ->all();
    }
}
