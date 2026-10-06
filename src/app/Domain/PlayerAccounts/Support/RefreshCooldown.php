<?php

namespace App\Domain\PlayerAccounts\Support;

use App\Domain\PlayerAccounts\Data\RefreshResultData;
use App\Domain\PlayerAccounts\Enums\RefreshOutcome;
use Illuminate\Support\Facades\RateLimiter;

/**
 * The `coc-refresh` limits (specs/04 §4, FR-COC-9, P2-20): one manual refresh per user and account
 * per `coc.sync.manual_cooldown`, and `coc.sync.manual_per_hour` per user across all their
 * accounts, so holding many rows never multiplies a user's API spend. Both are counted before the
 * call, so parallel clicks reach the API once, and given back when nothing was stored.
 */
final class RefreshCooldown
{
    private const HOUR = 3600;

    /**
     * Takes one refresh; null when it may go ahead, else the refusal with its wait.
     */
    public static function take(int $userId, int $accountId): ?RefreshResultData
    {
        $account = self::accountKey($userId, $accountId);

        if (RateLimiter::hit($account, (int) config('coc.sync.manual_cooldown')) > 1) {
            return new RefreshResultData(RefreshOutcome::CoolingDown, max(1, RateLimiter::availableIn($account)));
        }

        $user = self::userKey($userId);

        if (RateLimiter::hit($user, self::HOUR) > (int) config('coc.sync.manual_per_hour')) {
            // Not taken after all, so this account is not cooling down.
            RateLimiter::clear($account);

            return new RefreshResultData(RefreshOutcome::TooManyRefreshes, max(1, RateLimiter::availableIn($user)));
        }

        return null;
    }

    public static function giveBack(int $userId, int $accountId): void
    {
        RateLimiter::clear(self::accountKey($userId, $accountId));
        RateLimiter::decrement(self::userKey($userId), self::HOUR);
    }

    /**
     * Seconds until this account may be refreshed again, 0 when it is free.
     */
    public static function wait(int $userId, int $accountId): int
    {
        $key = self::accountKey($userId, $accountId);

        return RateLimiter::tooManyAttempts($key, 1) ? max(1, RateLimiter::availableIn($key)) : 0;
    }

    private static function accountKey(int $userId, int $accountId): string
    {
        return "coc-refresh:{$userId}:{$accountId}";
    }

    private static function userKey(int $userId): string
    {
        return "coc-refresh-user:{$userId}";
    }
}
