<?php

namespace App\Domain\PlayerAccounts;

use App\Domain\Auth\Contracts\DeletionHold;
use App\Domain\Auth\Contracts\DeletionStep;
use App\Domain\PlayerAccounts\Events\CocAccountVerified;
use App\Domain\PlayerAccounts\Listeners\SendDisputeNotice;
use App\Domain\PlayerAccounts\Listeners\SendOwnershipNotice;
use App\Domain\PlayerAccounts\Listeners\StartAccountSync;
use App\Domain\PlayerAccounts\Models\CocAccount;
use App\Domain\PlayerAccounts\Models\CocAccountDispute;
use App\Domain\PlayerAccounts\Policies\CocAccountDisputePolicy;
use App\Domain\PlayerAccounts\Policies\CocAccountPolicy;
use App\Domain\PlayerAccounts\Services\AccountDeletionHooks;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class PlayerAccountsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Auth's deletion pipeline reaches this module only through its contracts (specs/05 §2).
        $this->app->tag([AccountDeletionHooks::class], [DeletionHold::HOLD_TAG, DeletionStep::STEP_TAG]);
    }

    public function boot(): void
    {
        Gate::policy(CocAccount::class, CocAccountPolicy::class);
        Gate::policy(CocAccountDispute::class, CocAccountDisputePolicy::class);
        Event::subscribe(SendOwnershipNotice::class);
        Event::subscribe(SendDisputeNotice::class);
        Event::listen(CocAccountVerified::class, StartAccountSync::class);
    }
}
