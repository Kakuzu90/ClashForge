<?php

namespace App\Domain\Notifications\Listeners;

use App\Domain\Auth\Events\EmailVerified;
use App\Domain\Auth\Events\PasswordChanged;
use App\Domain\Auth\Events\UnrecognisedDeviceSignedIn;
use App\Domain\Media\Events\MediaRetriesExhausted;
use App\Domain\Notifications\Data\InAppMessageData;
use App\Domain\Notifications\Enums\NotificationType;
use App\Domain\Notifications\Services\Notifier;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Events\Dispatcher;

/**
 * In-app notices for events of the edge modules, which cannot call Notifications themselves
 * (specs/05 §2). Their emails, where there is one, are sent by the module that owns the event.
 */
class WriteInAppNotice implements ShouldQueue
{
    public string $queue = 'high';

    public function __construct(private readonly Notifier $notifier) {}

    public function handleEmailVerified(EmailVerified $event): void
    {
        $this->send($event->userId, new InAppMessageData(NotificationType::EmailVerified));
    }

    public function handlePasswordChanged(PasswordChanged $event): void
    {
        $this->send($event->userId, new InAppMessageData(NotificationType::PasswordChanged));
    }

    public function handleUnrecognisedDevice(UnrecognisedDeviceSignedIn $event): void
    {
        $this->send($event->userId, new InAppMessageData(NotificationType::NewDeviceSignIn, [
            'device' => $event->device,
            'country' => $event->country,
        ]));
    }

    public function handleMediaRetriesExhausted(MediaRetriesExhausted $event): void
    {
        $this->send($event->userId, new InAppMessageData(NotificationType::MediaProcessingFailed, [
            'collection' => $event->collection->value,
            'media' => $event->mediaUlid,
        ]));
    }

    private function send(int $userId, InAppMessageData $message): void
    {
        $user = User::query()->find($userId);

        // A deleted account reads nothing; there is no one to tell.
        if ($user !== null) {
            $this->notifier->send($user, $message);
        }
    }

    /**
     * @return array<class-string, string>
     */
    public function subscribe(Dispatcher $events): array
    {
        return [
            EmailVerified::class => 'handleEmailVerified',
            PasswordChanged::class => 'handlePasswordChanged',
            UnrecognisedDeviceSignedIn::class => 'handleUnrecognisedDevice',
            MediaRetriesExhausted::class => 'handleMediaRetriesExhausted',
        ];
    }
}
