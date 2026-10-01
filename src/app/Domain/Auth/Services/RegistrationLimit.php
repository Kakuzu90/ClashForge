<?php

namespace App\Domain\Auth\Services;

use App\Support\Privacy\IpHash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

/**
 * The `register` limit of specs/04 §4: accepted sign-ups per IP and hour. Counted after the form
 * passes validation, so typos are free; every attempt has its own looser cap on the route.
 */
class RegistrationLimit
{
    private const HOUR = 3600;

    public function ensureAllowed(?string $ip): void
    {
        $key = self::key($ip);

        if (RateLimiter::tooManyAttempts($key, (int) config('platform.auth.register_per_hour'))) {
            Log::channel('security')->warning('auth.rate_limited', ['limiter' => 'register', 'ip_hash' => IpHash::of($ip)]);

            $seconds = RateLimiter::availableIn($key);
            $wait = $seconds >= 120 ? ceil($seconds / 60).' minutes' : "{$seconds} seconds";

            throw ValidationException::withMessages(['email' => "Too many attempts. Try again in {$wait}."]);
        }
    }

    public function hit(?string $ip): void
    {
        RateLimiter::hit(self::key($ip), self::HOUR);
    }

    private static function key(?string $ip): string
    {
        return 'register:accepted:'.IpHash::of($ip);
    }
}
