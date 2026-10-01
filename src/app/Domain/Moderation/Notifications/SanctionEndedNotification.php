<?php

namespace App\Domain\Moderation\Notifications;

use App\Domain\Moderation\Enums\SanctionType;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * "Sanction lifted / expired" (specs/16 §2, I + E): the account is back to normal.
 */
class SanctionEndedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly SanctionType $type,
        public readonly bool $expired,
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
        $what = $this->type === SanctionType::Ban ? 'ban' : 'suspension';
        $how = $this->expired ? "Your {$what} has ended." : "Your {$what} has been lifted.";

        return (new MailMessage)
            ->subject("Your Clash Commons {$what} is over")
            ->greeting("Hi {$notifiable->username},")
            ->line($how.' Your account works as normal again.')
            ->action('Open Clash Commons', route('home'))
            ->salutation('Clash Commons');
    }
}
