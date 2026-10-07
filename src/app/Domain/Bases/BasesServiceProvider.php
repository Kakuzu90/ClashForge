<?php

namespace App\Domain\Bases;

use App\Domain\Bases\Listeners\DropLostCredits;
use App\Domain\Bases\Listeners\ForgetCachedFeeds;
use App\Domain\Bases\Listeners\PublishWhenMediaReady;
use App\Domain\Bases\Models\BaseLayout;
use App\Domain\Bases\Policies\BaseLayoutPolicy;
use App\Domain\Media\Events\MediaReady;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class BasesServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Gate::policy(BaseLayout::class, BaseLayoutPolicy::class);
        Event::listen(MediaReady::class, PublishWhenMediaReady::class);
        Event::subscribe(DropLostCredits::class);
        Event::subscribe(ForgetCachedFeeds::class);
    }
}
