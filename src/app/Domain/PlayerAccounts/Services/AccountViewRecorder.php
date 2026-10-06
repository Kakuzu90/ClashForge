<?php

namespace App\Domain\PlayerAccounts\Services;

use App\Domain\CocIntegration\Enums\SyncResourceType;
use App\Domain\CocIntegration\Enums\SyncTier;
use App\Domain\CocIntegration\Services\SyncSchedule;
use App\Domain\PlayerAccounts\Models\CocAccount;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\RateLimiter;

/**
 * "Viewed in the last 24 h" for the sync tiers (specs/09 §6, P2-20). Only signed-in views count:
 * the page is public and indexed, and crawlers would otherwise keep every account hot. A view is
 * written at most once per `coc.sync.view_record_seconds` per account, and moves a synced account
 * up to the hot tier at once. Each viewer records at most `coc.sync.views_per_viewer_per_hour`
 * accounts, so one scripted login cannot keep every account hot and spend the background budget.
 */
class AccountViewRecorder
{
    private const HOUR = 3600;

    public function __construct(private readonly SyncSchedule $schedule) {}

    public function record(?User $viewer, string $accountUlid): void
    {
        $marker = "coc-account-viewed:{$accountUlid}";

        if ($viewer === null || ! Cache::add($marker, true, (int) config('coc.sync.view_record_seconds'))) {
            return;
        }

        // Counts first and compares after, as `ClaimLimits` does. Over the cap, the marker goes, so
        // the next viewer's view still counts.
        if (RateLimiter::hit("coc-account-views:{$viewer->id}", self::HOUR) > (int) config('coc.sync.views_per_viewer_per_hour')) {
            Cache::forget($marker);

            return;
        }

        $account = CocAccount::query()->where('ulid', $accountUlid)->first(['id', 'user_id', 'status']);

        if ($account === null) {
            return;
        }

        // Not a change to the account, so `updated_at` stays.
        CocAccount::query()->whereKey($account->id)->toBase()->update(['last_viewed_at' => Date::now()]);

        if ($account->user_id !== null && in_array($account->status, AccountSyncService::SYNCED, true)) {
            $this->schedule->promote(SyncResourceType::CocAccount, $account->id, SyncTier::Hot, (int) config('coc.sync.claim_seconds'));
        }
    }
}
