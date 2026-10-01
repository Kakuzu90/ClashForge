<?php

namespace App\Domain\Auth\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The requester's side of a taken address (specs/23 §1): the form answered as for any address,
 * so the current inbox learns that the change cannot go through.
 */
class EmailChangeRejectedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly string $newEmail)
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
            ->subject('Your Clash Commons email was not changed')
            ->greeting("Hi {$notifiable->username},")
            ->line("You asked to change your email to {$this->newEmail}. That address cannot be used for your account, so no link was sent and your email has not changed.")
            ->line('To use a different address, start again from your security settings.')
            ->action('Security settings', route('settings.security.edit'))
            ->salutation('Clash Commons');
    }
}
