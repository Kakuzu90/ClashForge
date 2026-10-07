<?php

namespace App\Domain\Bases\Services;

use App\Domain\Bases\Data\FeedFiltersData;
use App\Domain\PlayerAccounts\Services\AccountCredits;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * The signed-in home feed's starting Town Hall filter (specs/17 §6): the featured account's level
 * ± `bases.feed.default_th_spread`, within the levels a base may have. Shown on the page as a chip
 * the viewer can remove (P3-03).
 */
class FeedDefaults
{
    public function __construct(private readonly AccountCredits $accounts) {}

    public function forViewer(?Authenticatable $viewer, FeedFiltersData $filters): FeedFiltersData
    {
        $level = $viewer === null ? null : $this->accounts->featuredThLevel((int) $viewer->getAuthIdentifier());

        if ($level === null) {
            return $filters;
        }

        $spread = (int) config('bases.feed.default_th_spread');
        $min = max((int) config('bases.th_min'), $level - $spread);
        $max = min((int) config('bases.th_max'), $level + $spread);

        if ($min > $max) {
            return $filters;
        }

        return new FeedFiltersData($min, $max, $filters->category, $filters->tag, $filters->minLikes, $filters->hasVideo, $filters->sort);
    }
}
