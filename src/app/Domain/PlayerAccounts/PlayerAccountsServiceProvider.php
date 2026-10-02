<?php

namespace App\Domain\PlayerAccounts;

use App\Domain\PlayerAccounts\Events\CocAccountVerified;
use App\Domain\PlayerAccounts\Listeners\SendOwnershipNotice;
use App\Domain\PlayerAccounts\Listeners\StartAccountSync;
use App\Domain\PlayerAccounts\Models\CocAccount;
use App\Domain\PlayerAccounts\Models\CocAccountDispute;
use App\Domain\PlayerAccounts\Policies\CocAccountDisputePolicy;
use App\Domain\PlayerAccounts\Policies\CocAccountPolicy;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class PlayerAccountsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Gate::policy(CocAccount::class, CocAccountPolicy::class);
        Gate::policy(CocAccountDispute::class, CocAccountDisputePolicy::class);
        Event::subscribe(SendOwnershipNotice::class);
        Event::listen(CocAccountVerified::class, StartAccountSync::class);
    }
}
