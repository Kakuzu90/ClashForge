<?php

namespace App\Domain\Notifications\Services;

use App\Domain\Notifications\Contracts\InAppNotification;
use App\Models\User;
use Illuminate\Notifications\Notification;

/**
 * Laravel notification channel for the notification centre: `via()` lists this class. Runs in
 * the notification's queued job, like the mail channel beside it.
 */
class InAppChannel
{
    public function __construct(private readonly Notifier $notifier) {}

    public function send(mixed $notifiable, Notification $notification): void
    {
        if (! $notifiable instanceof User || ! $notification instanceof InAppNotification) {
            return;
        }

        $this->notifier->send($notifiable, $notification->toInApp($notifiable));
    }
}
