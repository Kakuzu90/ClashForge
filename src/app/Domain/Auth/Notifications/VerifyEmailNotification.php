<?php

namespace App\Domain\Auth\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\URL;

/**
 * "Email verification link" (specs/16 §2, E*): a signed link that expires in 60 minutes
 * (FR-AUTH-3), addressed by ULID and a hash of the email, so a link for an old address stops
 * working once the address changes (P1-10).
 */
class VerifyEmailNotification extends Notification implements ShouldQueue
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

    public static function url(User $user): string
    {
        return URL::temporarySignedRoute(
            'verification.verify',
            Date::now()->addMinutes((int) config('platform.auth.verification_link_minutes')),
            ['ulid' => $user->ulid, 'hash' => sha1(strtolower($user->email))],
        );
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        $minutes = (int) config('platform.auth.verification_link_minutes');

        return (new MailMessage)
            ->subject('Confirm your Clash Commons email')
            ->greeting("Hi {$notifiable->username},")
            ->line('Confirm this address to finish setting up your Clash Commons account.')
            ->action('Confirm your email', self::url($notifiable))
            ->line("The link expires in {$minutes} minutes. You can ask for a new one after signing in.")
            ->line('If you did not create an account, ignore this email.')
            ->salutation('Clash Commons');
    }
}
