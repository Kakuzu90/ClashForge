<?php

namespace App\Domain\Notifications\Contracts;

use App\Domain\Notifications\Data\InAppMessageData;

/**
 * A Laravel notification that also lands in the notification centre: list InAppChannel in its
 * `via()` and describe the row here (specs/16 §1).
 */
interface InAppNotification
{
    public function toInApp(mixed $notifiable): InAppMessageData;
}
