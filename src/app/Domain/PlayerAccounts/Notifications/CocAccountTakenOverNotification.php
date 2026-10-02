<?php

namespace App\Domain\PlayerAccounts\Notifications;

use App\Domain\Notifications\Contracts\InAppNotification;
use App\Domain\Notifications\Data\InAppMessageData;
use App\Domain\Notifications\Enums\NotificationType;
use App\Domain\Notifications\Services\InAppChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * "Your verified account was claimed by someone else" (specs/16 §2, I + E*): sent whatever the
 * email preferences say. Both copies are worded by `NotificationType`, so they cannot drift apart.
 */
class CocAccountTakenOverNotification extends Notification implements InAppNotification, ShouldQueue
{
    use Queueable;

    /**
     * @param  array{tag: string, method: string}  $params
     */
    public function __construct(public readonly array $params)
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
        $notice = NotificationType::CocAccountTakenOver->render($this->params);

        return (new MailMessage)
            ->subject($notice->title)
            ->greeting("Hi {$notifiable->username},")
            ->line($notice->body)
            ->salutation('Clash Commons');
    }

    public function toInApp(mixed $notifiable): InAppMessageData
    {
        return new InAppMessageData(NotificationType::CocAccountTakenOver, $this->params);
    }
}
