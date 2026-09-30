<?php

use App\Domain\Auth\AuthServiceProvider;
use App\Domain\GameAssets\GameAssetsServiceProvider;
use App\Domain\Media\MediaServiceProvider;
use App\Providers\AppServiceProvider;
use App\Providers\FortifyServiceProvider;
use App\Providers\TypeScriptTransformerServiceProvider;

return [
    AppServiceProvider::class,
    TypeScriptTransformerServiceProvider::class,
    FortifyServiceProvider::class,
    AuthServiceProvider::class,
    MediaServiceProvider::class,
    GameAssetsServiceProvider::class,
];
