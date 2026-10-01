<?php

namespace App\Domain\Moderation\Notifications;

use App\Domain\Notifications\Contracts\InAppNotification;
use App\Domain\Notifications\Data\InAppMessageData;
use App\Domain\Notifications\Enums\NotificationType;
use App\Domain\Notifications\Services\InAppChannel;
use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * "Account suspended" (specs/16 §2, I + E*): the reason and the end date, FR-MOD-8. The appeal
 * link joins with appeals (P5-02).
 */
class AccountSuspendedNotification extends Notification implements InAppNotification, ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $reason,
        public readonly CarbonImmutable $endsAt,
    ) {
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
            ->subject('Your Clash Commons account is suspended')
            ->greeting("Hi {$notifiable->username},")
            ->line('Your account is suspended until '.$this->endsAt->utc()->format('j F Y \a\t H:i').' UTC.')
            ->line("Reason: {$this->reason}")
            ->line('Until then you can sign in to read your settings and notifications, but you cannot post or browse other players\' content.')
            ->salutation('Clash Commons');
    }

    public function toInApp(mixed $notifiable): InAppMessageData
    {
        return new InAppMessageData(NotificationType::AccountSuspended, ['reason' => $this->reason, 'ends_at' => $this->endsAt->toIso8601String()]);
    }
}
