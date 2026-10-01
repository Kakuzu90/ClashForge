<?php

namespace App\Http\Middleware;

use App\Domain\Auth\Services\SessionService;
use App\Models\User;
use App\Support\Privacy\IpHash;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * A session ends 30 days after its sign-in however active it is (specs/04 §4); the idle limit is
 * the session lifetime. Sessions from before the clock existed start it on their next request.
 */
class EnforceAbsoluteSessionLifetime
{
    public function __construct(private readonly SessionService $sessions) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User || ! $request->hasSession()) {
            return $next($request);
        }

        $session = $request->session();
        $signedInAt = $session->get(SessionService::SIGNED_IN_AT);

        if (! is_int($signedInAt)) {
            $session->put(SessionService::SIGNED_IN_AT, Date::now()->getTimestamp());

            return $next($request);
        }

        if (! $this->sessions->pastAbsoluteLifetime($signedInAt)) {
            return $next($request);
        }

        Log::channel('security')->info('auth.session_expired', ['user' => $user->ulid, 'ip_hash' => IpHash::of($request->ip())]);

        // Logout also forgets the remember cookie, so the browser cannot sign straight back in.
        Auth::guard('web')->logout();
        $session->invalidate();
        $session->regenerateToken();

        if ($request->expectsJson() || $request->is('uploads/*')) {
            return response()->json(['message' => __('auth.session_expired')], 401);
        }

        // 303, not 302: this runs outside Inertia's middleware, and a PUT or DELETE answered with
        // 302 would be repeated against /login.
        return redirect()->route('login', status: 303)->with('status', __('auth.session_expired'));
    }
}
