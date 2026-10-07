<?php

namespace App\Domain\Search;

use App\Domain\Search\Contracts\SearchService;
use App\Domain\Search\Services\PostgresSearchDriver;
use Illuminate\Support\ServiceProvider;

class SearchServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Postgres full text until the specs/17 §7 triggers fire; callers only see the contract.
        $this->app->bind(SearchService::class, PostgresSearchDriver::class);
    }
}
