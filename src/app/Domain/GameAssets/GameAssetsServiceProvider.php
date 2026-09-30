<?php

namespace App\Domain\GameAssets;

use App\Domain\GameAssets\Services\GameAssetResolver;
use Illuminate\Support\ServiceProvider;

class GameAssetsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Scoped: the manifest is read once per request or job, and a config change between
        // requests (the kill switch) is always picked up.
        $this->app->scoped(GameAssetResolver::class);
    }
}
