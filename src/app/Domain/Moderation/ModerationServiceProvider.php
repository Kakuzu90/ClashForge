<?php

namespace App\Domain\Moderation;

use App\Domain\Moderation\Listeners\SendSanctionNotice;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class ModerationServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Event::subscribe(SendSanctionNotice::class);
    }
}
