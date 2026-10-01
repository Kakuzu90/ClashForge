<?php

namespace App\Providers;

use App\Support\Privacy\IpHash;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Auth\Events\Authenticated;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Illuminate\Routing\Events\RouteMatched;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Immutable dates everywhere; tests freeze time through Date::now() (specs/05 §5).
        Date::use(CarbonImmutable::class);

        // Lazy loading, silently discarded and missing attributes throw outside production (specs/03 NFR-PERF-7).
        Model::shouldBeStrict(! $this->app->isProduction());

        $this->configureRateLimiting();

        /** @var list<string> $proxies */
        $proxies = config('platform.trusted_proxies');
        TrustProxies::at($proxies);

        // Every log line after routing carries the route, and after authentication the user id (specs/03 NFR-OBS-1).
        Event::listen(RouteMatched::class, fn (RouteMatched $event) => Context::add('route', $event->route->getName() ?? $event->route->uri()));
        Event::listen(Authenticated::class, fn (Authenticated $event) => Context::add('user_id', $event->user->getAuthIdentifier()));
    }

    /**
     * Named limiters from specs/04 §4, defined in one place and backed by the Cache facade.
     */
    private function configureRateLimiting(): void
    {
        RateLimiter::for('upload-intent', fn (Request $request): Limit => Limit::perHour((int) config('media.rate_limits.intents_per_hour'))
            ->by('user:'.($request->user()?->getAuthIdentifier() ?? $request->ip())));

        // ip + email buckets stop guessing one account from one address without letting a stranger
        // lock out an email they do not own; the per-IP ceiling stops one client rotating emails to
        // make the server hash without limit. Guessing one account from many IPs is not capped here:
        // failed logins go to the security log, which alerts on spikes (specs/11 §3).
        RateLimiter::for('login', fn (Request $request): array => [
            Limit::perMinute((int) config('platform.auth.login_per_minute'))->by('login:m:'.$this->emailKey($request))->response($this->throttledForm('login')),
            Limit::perHour((int) config('platform.auth.login_per_hour'))->by('login:h:'.$this->emailKey($request))->response($this->throttledForm('login')),
            Limit::perMinute((int) config('platform.auth.login_per_ip_per_minute'))->by('login:ip:'.$request->ip())->response($this->throttledForm('login')),
        ]);

        // Backstop on every signed-in write (specs/04 §4). An Inertia form gets the wait as a flash
        // error on the page it came from, not a bare 429.
        RateLimiter::for('global-write', fn (Request $request): Limit => Limit::perMinute((int) config('platform.rate_limits.global_write_per_minute'))
            ->by('global-write:'.($request->user()?->getAuthIdentifier() ?? $request->ip()))
            ->response(function (Request $request, array $headers): Response {
                Log::channel('security')->warning('auth.rate_limited', ['limiter' => 'global-write', 'ip_hash' => IpHash::of($request->ip())]);

                if ($request->expectsJson()) {
                    return response()->json(['message' => 'Too many changes. Wait a minute and try again.'], 429, $headers);
                }

                return back()->with('error', 'Too many changes. Wait a minute and try again.');
            }));

        // Admin list searches (specs/11 §2: a named limiter on search). Each page load counts twice:
        // the page and its deferred rows. A GET cannot redirect back on a breach (the previous URL
        // is throttled too), so it is a bare 429 the page shows inline (useVisitError).
        RateLimiter::for('admin-search', fn (Request $request): Limit => Limit::perMinute((int) config('platform.rate_limits.admin_search_per_minute'))
            ->by('admin-search:'.($request->user()?->getAuthIdentifier() ?? $request->ip()))
            ->response(function (Request $request, array $headers): Response {
                Log::channel('security')->warning('auth.rate_limited', ['limiter' => 'admin-search', 'ip_hash' => IpHash::of($request->ip())]);

                return response('Too many searches.', 429, $headers);
            }));

        RateLimiter::for('password-reset', fn (Request $request): array => [
            Limit::perHour((int) config('platform.auth.password_reset_per_hour'))->by('password-reset:'.$this->emailKey($request))->response($this->throttledForm('password-reset')),
            Limit::perHour((int) config('platform.auth.password_reset_per_ip_per_hour'))->by('password-reset:ip:'.$request->ip())->response($this->throttledForm('password-reset')),
        ]);

        // Guessing the current password from a hijacked session: the confirm page and the
        // password change share one bucket per account (specs/11 "Authentication attacks").
        RateLimiter::for('password-confirm', function (Request $request): array {
            $key = 'password-confirm:'.($request->user()?->getAuthIdentifier() ?? $request->ip());
            $response = $this->throttledForm('password-confirm', $request->has('current_password') ? 'current_password' : 'password');

            return [
                Limit::perMinute((int) config('platform.auth.password_confirm_per_minute'))->by($key.':m')->response($response),
                Limit::perHour((int) config('platform.auth.password_confirm_per_hour'))->by($key.':h')->response($response),
            ];
        });
    }

    private function emailKey(Request $request): string
    {
        return Str::lower(trim($request->string('email')->toString())).'|'.$request->ip();
    }

    /**
     * Auth forms are Inertia pages: a bare 429 would open Inertia's error modal, so the limit comes
     * back as a field error with the wait time. Every breach is a security event (specs/11 §3).
     *
     * @return Closure(Request, array<string, string>): Response
     */
    private function throttledForm(string $limiter, string $field = 'email'): Closure
    {
        return function (Request $request, array $headers) use ($limiter, $field): Response {
            Log::channel('security')->warning('auth.rate_limited', ['limiter' => $limiter, 'ip_hash' => IpHash::of($request->ip())]);

            $seconds = (int) ($headers['Retry-After'] ?? 60);
            $wait = $seconds >= 120 ? ceil($seconds / 60).' minutes' : "{$seconds} seconds";

            return back()
                ->withInput($request->only('email'))
                ->withErrors([$field => "Too many attempts. Try again in {$wait}."]);
        };
    }
}
