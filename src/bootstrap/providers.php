<?php

use App\Domain\GameAssets\GameAssetsServiceProvider;
use App\Domain\Media\MediaServiceProvider;
use App\Providers\AppServiceProvider;
use App\Providers\TypeScriptTransformerServiceProvider;

return [
    AppServiceProvider::class,
    TypeScriptTransformerServiceProvider::class,
    MediaServiceProvider::class,
    GameAssetsServiceProvider::class,
];
