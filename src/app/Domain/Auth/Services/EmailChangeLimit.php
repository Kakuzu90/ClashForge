<?php

namespace App\Domain\Auth\Services;

use App\Models\User;
use App\Support\Privacy\IpHash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

/**
 * The `email-change` limit of specs/04 §4: accepted requests and resends per account and hour.
 * Counted after the form passes validation, so typos are free, as for registration.
 */
class EmailChangeLimit
{
    private const HOUR = 3600;

    /**
     * @throws ValidationException
     */
    public function hit(User $user, ?string $ip): void
    {
        $key = 'email-change:'.$user->id;

        if (RateLimiter::tooManyAttempts($key, (int) config('platform.auth.email_change_per_hour'))) {
            Log::channel('security')->warning('auth.rate_limited', ['limiter' => 'email-change', 'user' => $user->ulid, 'ip_hash' => IpHash::of($ip)]);

            $seconds = RateLimiter::availableIn($key);
            $wait = $seconds >= 120 ? ceil($seconds / 60).' minutes' : "{$seconds} seconds";

            throw ValidationException::withMessages(['email' => "Too many attempts. Try again in {$wait}."]);
        }

        RateLimiter::hit($key, self::HOUR);
    }
}
