<?php

namespace App\Domain\Auth\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Security email, always sent (specs/16 §1), on the `high` queue (specs/20 §1).
 */
class ResetPasswordNotification extends ResetPassword implements ShouldQueue
{
    use Queueable;

    public function __construct(string $token)
    {
        parent::__construct($token);
        $this->onQueue('high');
    }

    /**
     * @param  mixed  $notifiable
     */
    public function toMail($notifiable): MailMessage
    {
        $minutes = (int) config('auth.passwords.'.config('auth.defaults.passwords').'.expire');

        return (new MailMessage)
            ->subject('Reset your Clash Commons password')
            ->greeting("Hi {$notifiable->username},")
            ->line('Someone asked to reset the password for your Clash Commons account.')
            ->action('Choose a new password', $this->resetUrl($notifiable))
            ->line("The link works once and expires in {$minutes} minutes. Resetting signs you out everywhere.")
            ->line('If you did not ask for this, ignore this email. Your password stays as it is.')
            ->salutation('Clash Commons');
    }
}
