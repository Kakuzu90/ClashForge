<?php

namespace App\Providers;

use Carbon\CarbonImmutable;
use Illuminate\Auth\Events\Authenticated;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Illuminate\Routing\Events\RouteMatched;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

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
    }
}
