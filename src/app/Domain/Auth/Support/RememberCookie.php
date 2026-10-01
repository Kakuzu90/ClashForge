<?php

namespace App\Domain\Auth\Support;

use App\Models\User;
use Illuminate\Auth\SessionGuard;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * Remember-me cookies hold the user's single remember token (specs/04 §4). Ending other sessions
 * must also kill their cookies, or those browsers sign straight back in, so the token is cycled.
 * This browser's cookie dies with it and is not re-issued: a fresh cookie would restart the 30
 * days the password sign-in started. This browser stays signed in for its session.
 */
final class RememberCookie
{
    public static function cycle(User $user): void
    {
        $guard = Auth::guard('web');
        $token = Str::random(60);

        $user->setRememberToken($token);
        $guard->getProvider()->updateRememberToken($user, $token);

        if ($guard instanceof SessionGuard && $guard->getRequest()->cookies->has($guard->getRecallerName())) {
            $guard->getCookieJar()->queue($guard->getCookieJar()->forget($guard->getRecallerName()));
        }
    }
}
