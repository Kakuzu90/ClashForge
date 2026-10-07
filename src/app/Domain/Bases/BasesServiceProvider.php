<?php

namespace App\Domain\Bases;

use App\Domain\Bases\Listeners\DropLostCredits;
use App\Domain\Bases\Listeners\ForgetCachedFeeds;
use App\Domain\Bases\Listeners\PublishWhenMediaReady;
use App\Domain\Bases\Models\BaseLayout;
use App\Domain\Bases\Policies\BaseLayoutPolicy;
use App\Domain\Bases\Services\BaseSearchSource;
use App\Domain\Media\Events\MediaReady;
use App\Domain\Search\Contracts\SearchSource;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class BasesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->tag([BaseSearchSource::class], [SearchSource::TAG]);
    }

    public function boot(): void
    {
        Gate::policy(BaseLayout::class, BaseLayoutPolicy::class);
        Event::listen(MediaReady::class, PublishWhenMediaReady::class);
        Event::subscribe(DropLostCredits::class);
        Event::subscribe(ForgetCachedFeeds::class);
    }
}
