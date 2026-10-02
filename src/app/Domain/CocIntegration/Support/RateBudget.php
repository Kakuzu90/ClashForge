<?php

namespace App\Domain\CocIntegration\Support;

use App\Domain\CocIntegration\Enums\CocFailureReason;
use App\Domain\CocIntegration\Enums\CocPriority;
use App\Domain\CocIntegration\Exceptions\CocApiFailure;
use Illuminate\Support\Facades\RateLimiter;

/**
 * The global budget of specs/09 §4, per second and per minute, through the cache-backed
 * RateLimiter (no Redis, specs/21 §1). Background calls also count against their own bucket,
 * capped at (1 - interactive_share) of the global numbers, so interactive calls always find the
 * reserved share free. Over budget is a Throttled failure with the wait, never a sleep.
 */
final class RateBudget
{
    private const WINDOWS = ['second' => 1, 'minute' => 60];

    /**
     * @throws CocApiFailure throttled
     */
    public function take(CocPriority $priority): void
    {
        $limits = $this->limits($priority);
        $waits = [];

        foreach ($limits as $key => [$max]) {
            if (RateLimiter::tooManyAttempts($key, $max)) {
                $waits[] = RateLimiter::availableIn($key);
            }
        }

        if ($waits !== []) {
            throw new CocApiFailure(CocFailureReason::Throttled, max(1, max($waits)), "{$priority->value} budget spent");
        }

        foreach ($limits as $key => [, $decay]) {
            RateLimiter::hit($key, $decay);
        }
    }

    /**
     * Calls a background job may still make this minute (specs/09 §6: the sync scheduler sizes
     * its batch from this).
     */
    public function backgroundRemainingThisMinute(): int
    {
        $remaining = PHP_INT_MAX;

        foreach ($this->limits(CocPriority::Background) as $key => [$max, $decay]) {
            if ($decay === self::WINDOWS['minute']) {
                $remaining = min($remaining, RateLimiter::remaining($key, $max));
            }
        }

        return max(0, $remaining);
    }

    /**
     * @return array<string, array{int, int}> limiter key => [max attempts, decay seconds]
     */
    private function limits(CocPriority $priority): array
    {
        $global = [
            'second' => (int) config('coc.rate.global_per_second'),
            'minute' => (int) config('coc.rate.global_per_minute'),
        ];
        $limits = [];

        foreach (self::WINDOWS as $per => $decay) {
            $limits[CocCacheKeys::rate('global', $per)] = [$global[$per], $decay];
        }

        if ($priority === CocPriority::Background) {
            $share = 1 - (float) config('coc.rate.interactive_share');

            foreach (self::WINDOWS as $per => $decay) {
                $limits[CocCacheKeys::rate('background', $per)] = [max(1, (int) floor($global[$per] * $share)), $decay];
            }
        }

        return $limits;
    }
}
