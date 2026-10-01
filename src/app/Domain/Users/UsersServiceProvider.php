<?php

namespace App\Domain\Users;

use App\Domain\Auth\Events\UserRegistered;
use App\Domain\Media\Events\MediaReady;
use App\Domain\Users\Listeners\CreateProfileForNewAccount;
use App\Domain\Users\Listeners\ForgetProfileWhenAvatarReady;
use App\Domain\Users\Models\PrivacySettings;
use App\Domain\Users\Models\Profile;
use App\Domain\Users\Policies\PrivacySettingsPolicy;
use App\Domain\Users\Policies\ProfilePolicy;
use App\Domain\Users\Services\PrivacyPolicyResolver;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class UsersServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // One per request, so its memo (specs/21 L3) never outlives the request.
        $this->app->scoped(PrivacyPolicyResolver::class);
    }

    public function boot(): void
    {
        Gate::policy(Profile::class, ProfilePolicy::class);
        Gate::policy(PrivacySettings::class, PrivacySettingsPolicy::class);

        Event::listen(MediaReady::class, ForgetProfileWhenAvatarReady::class);
        Event::listen(UserRegistered::class, CreateProfileForNewAccount::class);
    }
}
