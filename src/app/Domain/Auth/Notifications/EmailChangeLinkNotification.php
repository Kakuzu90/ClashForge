<?php

namespace App\Domain\Auth\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\URL;

/**
 * The link to the new address (FR-AUTH-8, specs/16 §2 E*). Signed for 60 minutes and addressed by
 * the account's ULID and a hash of this address, so a newer request (or a confirmed change) makes
 * it stop working. Built when the mail is sent: no signed URL waits in the queue.
 */
class EmailChangeLinkNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $username,
        public readonly string $ulid,
        public readonly string $email,
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

    public static function url(string $ulid, string $email): string
    {
        return URL::temporarySignedRoute(
            'settings.email.show',
            Date::now()->addMinutes((int) config('platform.auth.verification_link_minutes')),
            ['ulid' => $ulid, 'hash' => sha1(strtolower($email))],
        );
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        $minutes = (int) config('platform.auth.verification_link_minutes');

        return (new MailMessage)
            ->subject('Confirm your new Clash Commons email')
            ->greeting("Hi {$this->username},")
            ->line('You asked to use this address for your Clash Commons account. Confirm it to finish the change.')
            ->action('Confirm your new email', self::url($this->ulid, $this->email))
            ->line("The link expires in {$minutes} minutes and only works while you are signed in to that account.")
            ->line('If you did not ask for this, ignore this email. Nothing changes until the link is used.')
            ->salutation('Clash Commons');
    }
}
