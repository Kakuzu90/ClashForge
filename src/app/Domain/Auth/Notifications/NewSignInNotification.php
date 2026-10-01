<?php

namespace App\Domain\Auth\Notifications;

use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * "New sign-in from an unrecognised device" (specs/16 §2, specs/23 §1): security email, always
 * sent, on `high`. The session is not ended; the email points to the session list instead.
 */
class NewSignInNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $deviceLabel,
        public readonly ?string $country,
        public readonly CarbonImmutable $signedInAt,
    ) {
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
        $where = $this->country === null ? $this->deviceLabel : "{$this->deviceLabel}, {$this->country}";

        return (new MailMessage)
            ->subject('New sign-in to your Clash Commons account')
            ->greeting("Hi {$notifiable->username},")
            ->line("Your account was signed in from a device we have not seen before: {$where}, on ".$this->signedInAt->utc()->format('j F Y \a\t H:i').' UTC.')
            ->line('If this was you, there is nothing to do.')
            ->line('If it was not, sign that device out and change your password.')
            ->action('Review your sessions', route('settings.security.edit'))
            ->salutation('Clash Commons');
    }
}
