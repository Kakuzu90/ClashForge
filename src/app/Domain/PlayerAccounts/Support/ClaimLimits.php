<?php

namespace App\Domain\PlayerAccounts\Support;

use App\Domain\CocIntegration\Data\PlayerTag;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;

/**
 * The `coc-attach` and `coc-verify` limits (specs/04 §4, specs/09 §9), per user and hour.
 *
 * Attach counts distinct tags: the first preview or attach of a tag in the hour spends one
 * attempt, and going back to the same tag is free, so the three-step flow costs one attempt.
 * Verification counts every try, successful or not.
 */
final class ClaimLimits
{
    private const HOUR = 3600;

    /**
     * Counts first and compares after, so parallel requests cannot all pass a check made before
     * any of them counted.
     *
     * @return int|null seconds to wait when over the limit, null when the attempt may go ahead
     */
    public function attach(User $user, PlayerTag $tag): ?int
    {
        $key = "coc-attach:{$user->id}";
        $marker = "{$key}:{$tag->bare()}";

        if (! Cache::add($marker, true, self::HOUR)) {
            return null;
        }

        if (RateLimiter::hit($key, self::HOUR) > (int) config('coc.accounts.attach_per_hour')) {
            // Not counted after all, so the tag is not free next time.
            Cache::forget($marker);

            return max(1, RateLimiter::availableIn($key));
        }

        return null;
    }

    /**
     * @return int|null seconds to wait when over the limit, null when the attempt may go ahead
     */
    public function verify(User $user): ?int
    {
        $key = "coc-verify:{$user->id}";

        if (RateLimiter::hit($key, self::HOUR) > (int) config('coc.accounts.verify_per_hour')) {
            return max(1, RateLimiter::availableIn($key));
        }

        return null;
    }

    /**
     * True for the first refused attempt of a window: only that one becomes a claim row and a
     * security event, so hammering a limit cannot grow either without bound (specs/11 §3).
     *
     * @param  'attach'|'verify'  $limit
     */
    public function firstRefusal(User $user, string $limit, int $wait): bool
    {
        return Cache::add("coc-{$limit}:{$user->id}:refused", true, $wait);
    }
}
