<?php

namespace App\Domain\Notifications\Services;

use App\Domain\Notifications\Enums\NotificationCategory;
use App\Domain\Notifications\Enums\NotificationType;
use App\Domain\Notifications\Jobs\SendEmailNotificationJob;
use App\Domain\Notifications\Models\EmailDelivery;
use App\Domain\Notifications\Notifications\NonSecurityEmail;
use App\Domain\Notifications\Support\UnsubscribeCapability;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class EmailDeliveryService
{
    public function __construct(private readonly EmailPreferenceService $preferences) {}

    /**
     * Queues a non-security email for another module, which cannot dispatch this module's job
     * (specs/19). Preferences, the daily cap and the receipt are checked when it runs.
     *
     * @param  array<string, string|int|bool|null>  $params
     */
    public function queue(int $userId, NotificationType $type, string $eventKey, array $params = []): void
    {
        SendEmailNotificationJob::dispatch($userId, $type, $eventKey, $params);
    }

    /** @param array<string, string|int|bool|null> $params */
    public function send(int $userId, NotificationType $type, string $eventKey, array $params): void
    {
        if ($type->category() === NotificationCategory::Security) {
            return;
        }

        DB::transaction(function () use ($userId, $type, $eventKey, $params): void {
            // The account lock serializes cap reservations, preference saves and anonymisation.
            $user = User::query()->lockForUpdate()->find($userId);
            if ($user === null || ! $this->preferences->allowsEmail($user, $type->category())) {
                return;
            }

            if (EmailDelivery::query()->where('user_id', $userId)->where('type', $type->value)->where('event_key', $eventKey)->exists()) {
                return;
            }

            $counter = 'notifications:email:'.$userId.':'.Date::now()->utc()->format('Y-m-d');
            $sent = (int) Cache::remember($counter, (int) config('platform.notifications.email_counter_ttl'), fn (): int => EmailDelivery::query()
                ->where('user_id', $userId)->where('sent_at', '>=', Date::now()->utc()->startOfDay())->where('sent_at', '<', Date::now()->utc()->addDay()->startOfDay())->count());
            if ($sent >= (int) config('platform.notifications.email_per_day')) {
                return;
            }

            Cache::increment($counter);
            Mail::to($user->email)->send(new NonSecurityEmail($type->render($params), UnsubscribeCapability::urlFor($user)));

            (new EmailDelivery)->forceFill(['user_id' => $userId, 'type' => $type->value, 'event_key' => $eventKey, 'sent_at' => Date::now()])->save();
        });
    }
}
