<?php

namespace App\Domain\Notifications\Listeners;

use App\Domain\Media\Events\MediaRetriesExhausted;
use App\Domain\Notifications\Enums\NotificationType;
use App\Domain\Notifications\Jobs\SendEmailNotificationJob;

class QueueMediaFailureEmail
{
    public function handle(MediaRetriesExhausted $event): void
    {
        SendEmailNotificationJob::dispatch($event->userId, NotificationType::MediaProcessingFailed, $event->mediaUlid, ['collection' => $event->collection->value]);
    }
}
