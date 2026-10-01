<?php

namespace App\Domain\Auth\Notifications;

use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Date;

/**
 * "Password changed" (specs/16 §2): security email, always sent, on `high`. The in-app copy is
 * written by Notifications from the `PasswordChanged` event.
 */
class PasswordChangedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public readonly CarbonImmutable $changedAt;

    public function __construct()
    {
        $this->changedAt = Date::now()->toImmutable();
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
            ->subject('Your Clash Commons password was changed')
            ->greeting("Hi {$notifiable->username},")
            ->line('The password for your Clash Commons account was changed on '.$this->changedAt->utc()->format('j F Y \a\t H:i').' UTC. Every other device was signed out.')
            ->line('If you did not change it, reset your password now. That signs out every device, including the one that changed it.')
            ->action('Reset your password', route('password.request'))
            ->salutation('Clash Commons');
    }
}
