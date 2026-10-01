<?php

namespace App\Domain\Moderation\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * "Account banned" (specs/16 §2, E*): the reason, FR-MOD-8. Every session has already ended.
 */
class AccountBannedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly string $reason)
    {
        $this->onQueue('high');
    }

    /**
     * @return list<string>
     */
    public function via(mixed $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your Clash Commons account is banned')
            ->greeting("Hi {$notifiable->username},")
            ->line('Your account is banned and can no longer sign in. Every device it was signed in on has been signed out.')
            ->line("Reason: {$this->reason}")
            ->salutation('Clash Commons');
    }
}
