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
use Illuminate\Mail\Markdown;
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

        // Mail lines are Markdown: text that came from a person (an admin's message to a sanctioned
        // account) must show as typed, never as a link or a remote image (specs/11 "XSS").
        Markdown::withSecuredEncoding();

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
        // The public feeds (P3-03): filters that miss the cache run live, so they are bounded too.
        RateLimiter::for('feed', fn (Request $request): Limit => Limit::perMinute((int) config('bases.feed.requests_per_minute'))
            ->by($request->user() === null ? 'ip:'.$request->ip() : 'user:'.$request->user()->getAuthIdentifier()));

        // Search (specs/17 §4, P3-05): every search runs live past the first anonymous page.
        RateLimiter::for('search', fn (Request $request): Limit => $request->user() === null
            ? Limit::perMinute((int) config('platform.search.rate_limits.per_ip'))->by('ip:'.$request->ip())
            : Limit::perMinute((int) config('platform.search.rate_limits.per_user'))->by('user:'.$request->user()->getAuthIdentifier()));

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

        // Failed-job retry and delete per staff member (specs/04 §4, P2-19); each action can touch
        // a whole class, so it is kept well under the global write limit.
        RateLimiter::for('admin-failed-jobs', fn (Request $request): Limit => Limit::perMinute((int) config('platform.rate_limits.admin_failed_jobs_per_minute'))
            ->by('admin-failed-jobs:'.($request->user()?->getAuthIdentifier() ?? $request->ip()))
            ->response(function (Request $request, array $headers): Response {
                Log::channel('security')->warning('auth.rate_limited', ['limiter' => 'admin-failed-jobs', 'ip_hash' => IpHash::of($request->ip())]);

                return back()->with('error', 'Too many failed-job actions. Wait a minute and try again.');
            }));

        RateLimiter::for('password-reset', fn (Request $request): array => [
            Limit::perHour((int) config('platform.auth.password_reset_per_hour'))->by('password-reset:'.$this->emailKey($request))->response($this->throttledForm('password-reset')),
            Limit::perHour((int) config('platform.auth.password_reset_per_ip_per_hour'))->by('password-reset:ip:'.$request->ip())->response($this->throttledForm('password-reset')),
        ]);

        // Registration (specs/04 §4): every attempt per IP here; accepted sign-ups (3 / hour) are
        // counted by RegistrationLimit, so typos never lock a person out. Resends per account.
        RateLimiter::for('register', fn (Request $request): Limit => Limit::perHour((int) config('platform.auth.register_attempts_per_hour'))
            ->by('register:attempts:'.$request->ip())
            ->response($this->throttledForm('register')));
        RateLimiter::for('verify-email-resend', fn (Request $request): Limit => Limit::perHour((int) config('platform.auth.verify_resend_per_hour'))
            ->by('verify-email-resend:'.($request->user()?->getAuthIdentifier() ?? $request->ip()))
            ->response(function (Request $request, array $headers): Response {
                Log::channel('security')->warning('auth.rate_limited', ['limiter' => 'verify-email-resend', 'ip_hash' => IpHash::of($request->ip())]);

                return back()->with('error', 'You asked for a new link a few times already. Try again in an hour.');
            }));

        // Dispute answers and withdrawals per account (specs/04 §4, P2-16); opening also counts here,
        // and its own daily cap is counted by DisputeService on accepted disputes only. The open and
        // answer forms get a field error with the wait; withdraw is a button, so it gets a flash.
        RateLimiter::for('coc-dispute-write', fn (Request $request): Limit => Limit::perHour((int) config('coc.disputes.write_per_hour'))
            ->by('coc-dispute-write:'.($request->user()?->getAuthIdentifier() ?? $request->ip()))
            ->response(function (Request $request, array $headers): Response {
                Log::channel('security')->warning('auth.rate_limited', ['limiter' => 'coc-dispute-write', 'ip_hash' => IpHash::of($request->ip())]);

                $seconds = (int) ($headers['Retry-After'] ?? 60);
                $message = 'You changed your disputes a lot this hour. Try again in '.($seconds >= 120 ? ceil($seconds / 60).' minutes' : "{$seconds} seconds").'.';
                $field = match (true) {
                    $request->routeIs('disputes.store') => 'reason',
                    $request->routeIs('disputes.respond') => 'statement',
                    default => null,
                };

                return $field === null ? back()->with('error', $message) : back()->withErrors([$field => $message]);
            }));

        // Adding and removing account images (P2-23); the upload intents keep their own limit.
        RateLimiter::for('coc-account-images', fn (Request $request): Limit => Limit::perHour((int) config('coc.images.writes_per_hour'))
            ->by('coc-account-images:'.($request->user()?->getAuthIdentifier() ?? $request->ip()))
            ->response(function (Request $request): Response {
                Log::channel('security')->warning('auth.rate_limited', ['limiter' => 'coc-account-images', 'ip_hash' => IpHash::of($request->ip())]);

                return back()->with('error', 'You changed your images a lot this hour. Try again later.');
            }));

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
