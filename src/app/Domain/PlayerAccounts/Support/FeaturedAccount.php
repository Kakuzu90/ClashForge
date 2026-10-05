<?php

namespace App\Domain\PlayerAccounts\Support;

use App\Domain\PlayerAccounts\Enums\CocAccountStatus;
use App\Domain\PlayerAccounts\Jobs\RestoreFeaturedAccountJob;
use App\Domain\PlayerAccounts\Models\CocAccount;
use Illuminate\Database\Eloquent\Builder;

/**
 * The one featured account per user (FR-COC-12, `coc_accounts.is_featured`). Every caller holds the
 * user's lock (`UserStatusService::lockAccounts`), so two writers never race on the flag.
 */
final class FeaturedAccount
{
    /**
     * After the featured row was detached, superseded, transferred or suspended: the user's
     * earliest-verified remaining holding row takes the flag, so their profile keeps its card
     * (owner decision 2026-10-05, P2-14). A row another transaction has locked (a sync, a dispute)
     * is skipped rather than waited for, since waiting on a row while holding the user lock is the
     * reverse of the order verification takes (rows, then users). If that leaves the user without a
     * flag, a job sets it after commit.
     */
    public static function fallback(int $userId): void
    {
        if (self::has($userId)) {
            return;
        }

        $row = self::candidates($userId)->lock('for update skip locked')->first();
        if ($row !== null) {
            $row->forceFill(['is_featured' => true])->save();

            return;
        }

        if (self::candidates($userId)->exists()) {
            RestoreFeaturedAccountJob::dispatch($userId)->afterCommit();
        }
    }

    /**
     * The same choice, for a caller that already holds the user's holding rows and then the user.
     */
    public static function restore(int $userId): void
    {
        if (! self::has($userId)) {
            self::candidates($userId)->first()?->forceFill(['is_featured' => true])->save();
        }
    }

    private static function has(int $userId): bool
    {
        return CocAccount::query()->where('user_id', $userId)->where('is_featured', true)->exists();
    }

    /**
     * @return Builder<CocAccount>
     */
    private static function candidates(int $userId): Builder
    {
        return CocAccount::query()
            ->where('user_id', $userId)
            ->whereIn('status', CocAccountStatus::HOLDING)
            ->orderBy('verified_at')->orderBy('id');
    }
}
