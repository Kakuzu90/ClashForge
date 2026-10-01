<?php

namespace App\Http\Middleware;

use App\Domain\Auth\Enums\UserStatus;
use App\Domain\Auth\Services\UserStatusService;
use App\Models\User;
use App\Support\Privacy\IpHash;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Re-checks status on every request, so a sanction (or its expiry) applies on the next page load
 * (specs/23 §7). A banned account is signed out, remember cookie included. A suspended one
 * reads its own data only: everything but the routes below leads to the notice (specs/04 §1).
 */
class EnforceAccountStatus
{
    /**
     * Route names a suspended account may still reach.
     */
    private const SUSPENDED_ALLOWED = ['account.suspended', 'logout', 'settings.*', 'notifications.*'];

    public function __construct(private readonly UserStatusService $statuses) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return $next($request);
        }

        return match ($this->statuses->effectiveStatus($user)) {
            UserStatus::Banned => $this->signOut($request, $user, $next),
            UserStatus::Suspended => $request->routeIs(...self::SUSPENDED_ALLOWED) ? $next($request) : $this->toNotice($request),
            default => $next($request),
        };
    }

    private function signOut(Request $request, User $user, Closure $next): Response
    {
        Log::channel('security')->warning('auth.banned_session_ended', [
            'user' => $user->ulid,
            'ip_hash' => IpHash::of($request->ip()),
        ]);

        // Logout cycles the remember token, so the recaller cookie stops working too.
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if ($request->routeIs('notifications.unsubscribe.*')) {
            return $next($request);
        }

        if ($request->expectsJson() || $request->is('uploads/*')) {
            return response()->json(['message' => __('auth.banned')], 401);
        }

        return redirect()->route('login')->withErrors(['email' => __('auth.banned')]);
    }

    private function toNotice(Request $request): Response
    {
        if ($request->expectsJson() || $request->is('uploads/*')) {
            return response()->json(['message' => __('account.suspended')], 403);
        }

        return redirect()->route('account.suspended');
    }
}
