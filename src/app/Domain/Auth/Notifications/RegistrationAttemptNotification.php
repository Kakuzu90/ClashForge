<?php

namespace App\Domain\Auth\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Someone tried to register with an address that already has an account (specs/23 §1). The form
 * said nothing; this email is how the owner finds out (specs/11 "Account enumeration").
 */
class RegistrationAttemptNotification extends Notification implements ShouldQueue
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
            ->subject('Someone tried to sign up with your email')
            ->greeting("Hi {$notifiable->username},")
            ->line('Someone tried to create a Clash Commons account with this email address. It already belongs to your account, so no new account was made.')
            ->line('If it was you, sign in instead. If you forgot your password, you can reset it.')
            ->action('Reset your password', route('password.request'))
            ->line('If it was not you, there is nothing to do. Your account has not changed.')
            ->salutation('Clash Commons');
    }
}
