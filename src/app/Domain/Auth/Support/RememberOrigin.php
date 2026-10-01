<?php

namespace App\Domain\Auth\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;

/**
 * When the password sign-in behind a remember-me cookie happened (specs/04 §4: 30 days absolute).
 * The recaller itself carries no issue time, so a session started from it would start a fresh
 * 30 days; this encrypted cookie hands the original time on instead. Encrypted and MACed, so a
 * browser can replay an older time but never forge a newer one; without it, a remember-me
 * sign-in counts as already expired.
 */
final class RememberOrigin
{
    public const COOKIE = 'remember_since';

    public static function start(int $signedInAt): void
    {
        Cookie::queue(self::COOKIE, (string) $signedInAt, (int) config('auth.guards.web.remember'));
    }

    public static function of(Request $request): ?int
    {
        $value = $request->cookie(self::COOKIE);

        return is_string($value) && ctype_digit($value) ? (int) $value : null;
    }
}
