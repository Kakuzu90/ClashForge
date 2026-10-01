<?php

namespace App\Domain\Moderation\Notifications;

use App\Domain\Notifications\Contracts\InAppNotification;
use App\Domain\Notifications\Data\InAppMessageData;
use App\Domain\Notifications\Enums\NotificationType;
use App\Domain\Notifications\Services\InAppChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * "Account banned" (specs/16 §2, I + E*): the reason, FR-MOD-8. Every session has already ended;
 * the in-app copy is there should the ban be lifted.
 */
class AccountBannedNotification extends Notification implements InAppNotification, ShouldQueue
{
    use Queueable;

    public function __construct(public readonly string $reason)
    {
        $this->onQueue('high');
    }

    /**
     * @return list<string>
     */
    public function via(mixed $notifiable): array
    {
        return ['mail', InAppChannel::class];
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your Clash Commons account is banned')
            ->greeting("Hi {$notifiable->username},")
            ->line('Your account is banned and can no longer sign in. Every device it was signed in on has been signed out.')
            ->line("Reason: {$this->reason}")
            ->salutation('Clash Commons');
    }

    public function toInApp(mixed $notifiable): InAppMessageData
    {
        return new InAppMessageData(NotificationType::AccountBanned, ['reason' => $this->reason]);
    }
}
