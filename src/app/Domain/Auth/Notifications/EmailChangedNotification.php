<?php

namespace App\Domain\Auth\Notifications;

use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Date;

/**
 * "Email address changed" (specs/16 §2 E*), to the old address and to the new one. The new
 * address is masked, as everywhere it is shown back.
 */
class EmailChangedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public readonly CarbonImmutable $changedAt;

    public function __construct(
        public readonly string $username,
        public readonly string $newEmail,
        public readonly bool $toOldAddress,
    ) {
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
        $when = $this->changedAt->utc()->format('j F Y \a\t H:i').' UTC';
        $mail = (new MailMessage)
            ->subject('Your Clash Commons email was changed')
            ->greeting("Hi {$this->username},");

        if ($this->toOldAddress) {
            return $mail
                ->line("The email for your Clash Commons account was changed to {$this->newEmail} on {$when}. Every other device was signed out.")
                ->line('Emails about your account now go to the new address, not this one.')
                ->line('If you did not make this change, sign in and change your password, then change the email back.')
                ->salutation('Clash Commons');
        }

        return $mail
            ->line("This is now the email for your Clash Commons account, since {$when}. Every other device was signed out.")
            ->line('Sign-in, password resets and account emails use this address from now on.')
            ->salutation('Clash Commons');
    }
}
