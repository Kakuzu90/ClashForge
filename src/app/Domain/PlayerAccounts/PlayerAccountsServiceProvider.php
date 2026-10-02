<?php

namespace App\Domain\PlayerAccounts;

use App\Domain\PlayerAccounts\Models\CocAccount;
use App\Domain\PlayerAccounts\Policies\CocAccountPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class PlayerAccountsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Gate::policy(CocAccount::class, CocAccountPolicy::class);
    }
}
