<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Cache\RateLimiter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Per-IP limit for /health on its own cache store. The default store is the database, so the
 * framework throttle would turn a database outage into a 500 before the probe could answer 503,
 * and every ping would write a database row. If the limiter itself fails, the probe still runs.
 */
class ThrottleHealth
{
    public function handle(Request $request, Closure $next): Response
    {
        try {
            $limiter = new RateLimiter(Cache::store((string) config('platform.health.rate_limit_store')));
            $key = 'health:'.$request->ip();

            if ($limiter->tooManyAttempts($key, (int) config('platform.health.rate_limit_per_minute'))) {
                return response()->json(['message' => 'Too many requests.'], 429, ['Retry-After' => (string) $limiter->availableIn($key)]);
            }

            $limiter->hit($key, 60);
        } catch (Throwable) {
            // Fail open: a broken limiter must not hide the health state.
        }

        return $next($request);
    }
}
