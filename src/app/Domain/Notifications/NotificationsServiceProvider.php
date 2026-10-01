<?php

namespace App\Domain\Notifications;

use App\Domain\Media\Events\MediaRetriesExhausted;
use App\Domain\Notifications\Listeners\QueueMediaFailureEmail;
use App\Domain\Notifications\Listeners\WriteInAppNotice;
use App\Domain\Notifications\Models\Notification;
use App\Domain\Notifications\Models\NotificationPreference;
use App\Domain\Notifications\Policies\EmailPreferencePolicy;
use App\Domain\Notifications\Policies\NotificationPolicy;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class NotificationsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Gate::policy(Notification::class, NotificationPolicy::class);
        Gate::policy(NotificationPreference::class, EmailPreferencePolicy::class);
        Event::subscribe(WriteInAppNotice::class);
        Event::listen(MediaRetriesExhausted::class, QueueMediaFailureEmail::class);
    }
}
