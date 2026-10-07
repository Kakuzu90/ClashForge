<?php

use App\Domain\Auth\AuthServiceProvider;
use App\Domain\Bases\BasesServiceProvider;
use App\Domain\CocIntegration\CocIntegrationServiceProvider;
use App\Domain\GameAssets\GameAssetsServiceProvider;
use App\Domain\Media\MediaServiceProvider;
use App\Domain\Moderation\ModerationServiceProvider;
use App\Domain\Notifications\NotificationsServiceProvider;
use App\Domain\PlayerAccounts\PlayerAccountsServiceProvider;
use App\Domain\Search\SearchServiceProvider;
use App\Domain\Users\UsersServiceProvider;
use App\Providers\AppServiceProvider;
use App\Providers\AuthorizationServiceProvider;
use App\Providers\FortifyServiceProvider;
use App\Providers\TypeScriptTransformerServiceProvider;

return [
    AppServiceProvider::class,
    TypeScriptTransformerServiceProvider::class,
    FortifyServiceProvider::class,
    AuthServiceProvider::class,
    AuthorizationServiceProvider::class,
    MediaServiceProvider::class,
    UsersServiceProvider::class,
    ModerationServiceProvider::class,
    NotificationsServiceProvider::class,
    GameAssetsServiceProvider::class,
    CocIntegrationServiceProvider::class,
    PlayerAccountsServiceProvider::class,
    BasesServiceProvider::class,
    SearchServiceProvider::class,
];
