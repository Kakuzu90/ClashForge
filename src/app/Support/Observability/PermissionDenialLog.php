<?php

namespace App\Support\Observability;

use App\Support\Privacy\IpHash;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Writes `auth.permission_denied` to the `security` log (specs/11 §3), at most
 * `platform.security_log.denials_per_minute` lines per account (or IP) and route, so looping a
 * forbidden request cannot flood a 90-day log. The alert on denial spikes still fires: the first
 * lines of each minute get through.
 */
final class PermissionDenialLog
{
    /**
     * @param  array<string, mixed>  $extra
     */
    public static function record(Request $request, ?string $userUlid, array $extra = []): void
    {
        $ipHash = IpHash::of($request->ip());
        $route = $request->route()?->getName();
        $key = 'security-log:denied:'.($userUlid ?? $ipHash).':'.($route ?? $request->path());

        RateLimiter::attempt(
            $key,
            (int) config('platform.security_log.denials_per_minute'),
            fn () => Log::channel('security')->notice('auth.permission_denied', [
                'user' => $userUlid,
                'ip_hash' => $ipHash,
                ...$extra,
                'route' => $route,
            ]),
        );
    }
}
