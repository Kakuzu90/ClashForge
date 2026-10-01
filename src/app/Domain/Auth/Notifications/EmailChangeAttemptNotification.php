<?php

namespace App\Domain\Auth\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Someone tried to move another account onto an address that already has one (specs/23 §1).
 * The form said nothing; this email is how the owner finds out (specs/11 "Account enumeration").
 */
class EmailChangeAttemptNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct()
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
            ->subject('Someone tried to use your email on another account')
            ->greeting("Hi {$notifiable->username},")
            ->line('Someone asked to change the email of a different Clash Commons account to this address. It already belongs to your account, so nothing changed.')
            ->line('There is nothing to do. Your account has not changed.')
            ->salutation('Clash Commons');
    }
}
