<?php

namespace App\Domain\Media;

use App\Domain\Media\Contracts\MediaProcessor;
use App\Domain\Media\Contracts\MediaScanner;
use App\Domain\Media\Models\Media;
use App\Domain\Media\Policies\MediaPolicy;
use App\Domain\Media\Support\MediaProcessorRouter;
use App\Domain\Media\Support\NullMediaScanner;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class MediaServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(MediaProcessor::class, MediaProcessorRouter::class);
        $this->app->bind(MediaScanner::class, NullMediaScanner::class);
    }

    public function boot(): void
    {
        Gate::policy(Media::class, MediaPolicy::class);
    }
}
