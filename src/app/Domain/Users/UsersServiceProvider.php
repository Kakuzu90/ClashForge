<?php

namespace App\Domain\Users;

use App\Domain\Users\Models\Profile;
use App\Domain\Users\Policies\ProfilePolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class UsersServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Gate::policy(Profile::class, ProfilePolicy::class);
    }
}
