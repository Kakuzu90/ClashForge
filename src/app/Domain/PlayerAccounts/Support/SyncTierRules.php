<?php

namespace App\Domain\PlayerAccounts\Support;

use App\Domain\CocIntegration\Enums\SyncTier;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;

/**
 * The tier of a healthy account (specs/09 §6): hot when featured, viewed by a signed-in user in the
 * last `coc.sync.hot_viewed_hours` (P2-20), or its owner was active in the last
 * `coc.sync.hot_active_days`; warm within `warm_active_days`; cold otherwise. Frozen is the
 * schedule's.
 */
final class SyncTierRules
{
    public static function for(bool $featured, ?CarbonImmutable $ownerActiveAt, ?CarbonImmutable $viewedAt = null): SyncTier
    {
        $now = CarbonImmutable::instance(Date::now());

        return match (true) {
            $featured,
            $viewedAt?->gte($now->subHours((int) config('coc.sync.hot_viewed_hours'))) === true,
            $ownerActiveAt?->gte($now->subDays((int) config('coc.sync.hot_active_days'))) === true => SyncTier::Hot,
            $ownerActiveAt?->gte($now->subDays((int) config('coc.sync.warm_active_days'))) === true => SyncTier::Warm,
            default => SyncTier::Cold,
        };
    }
}
