<?php

namespace App\Domain\Notifications\Jobs;

use App\Domain\Notifications\Enums\NotificationType;
use App\Domain\Notifications\Services\EmailDeliveryService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class SendEmailNotificationJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 60;

    /** @var list<int> */
    public array $backoff = [60, 300, 900];

    /** @param array<string, string|int|bool|null> $params */
    public function __construct(public readonly int $userId, public readonly NotificationType $type, public readonly string $eventKey, public readonly array $params = [])
    {
        $this->onQueue('low');
        $this->afterCommit();
    }

    public function handle(EmailDeliveryService $delivery): void
    {
        $started = hrtime(true);
        $context = ['user_id' => $this->userId, 'type' => $this->type->value, 'event_key' => $this->eventKey];
        Log::info('notifications.email_started', $context);
        try {
            $delivery->send($this->userId, $this->type, $this->eventKey, $this->params);
        } finally {
            $seconds = (hrtime(true) - $started) / 1_000_000_000;
            Log::info('notifications.email_finished', [...$context, 'duration_seconds' => $seconds]);
            if ($seconds > (int) config('platform.notifications.email_slow_seconds')) {
                Log::warning('notifications.email_slow', $context);
            }
        }
    }
}
