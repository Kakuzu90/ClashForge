<?php

namespace App\Domain\CocIntegration;

use App\Domain\CocIntegration\Contracts\CocApiClient;
use App\Domain\CocIntegration\Services\CocKeyPool;
use App\Domain\CocIntegration\Support\CachedCocApiClient;
use App\Domain\CocIntegration\Support\CircuitBreaker;
use App\Domain\CocIntegration\Support\CocRequestLog;
use App\Domain\CocIntegration\Support\FakeCocApiClient;
use App\Domain\CocIntegration\Support\HttpCocApiClient;
use App\Domain\CocIntegration\Support\RateBudget;
use App\Domain\CocIntegration\Support\ResponseMapper;
use App\Domain\CocIntegration\Support\ThrottledCocApiClient;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;
use RuntimeException;

class CocIntegrationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Scoped: tokens are read once per request or job, and a rotated key list is picked up.
        $this->app->scoped(CocKeyPool::class);

        // One fake per process, so a test scripts the instance the lookups use.
        $this->app->singleton(FakeCocApiClient::class, fn (Application $app): FakeCocApiClient => new FakeCocApiClient(
            $app->make(ResponseMapper::class),
            (string) config('coc.fake.fixtures_path'),
            (string) config('coc.fake.valid_token'),
        ));

        // The fake must never answer for real players: resolving it in production throws, and
        // coc:check-health reports the misconfiguration as down.
        // http: Cached(Throttled(Http)), so a cache hit spends no budget (specs/09 §1). The fake
        // stays bare: tests script its failures directly.
        $this->app->bind(CocApiClient::class, fn (Application $app): CocApiClient => match (config('coc.driver')) {
            'http' => new CachedCocApiClient(
                new ThrottledCocApiClient($app->make(HttpCocApiClient::class), $app->make(CircuitBreaker::class), $app->make(RateBudget::class)),
                $app->make(ResponseMapper::class),
                $app->make(CocRequestLog::class),
            ),
            'fake' => $app->isProduction()
                ? throw new RuntimeException('COC_API_DRIVER=fake is not allowed in production.')
                : $app->make(FakeCocApiClient::class),
            default => throw new InvalidArgumentException('Unknown COC_API_DRIVER; use http or fake.'),
        });
    }
}
