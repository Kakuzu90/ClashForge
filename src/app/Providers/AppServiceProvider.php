<?php

namespace App\Providers;

use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
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
