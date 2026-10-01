<?php

namespace Database\Factories\Notifications;

use App\Domain\Notifications\Enums\NotificationType;
use App\Domain\Notifications\Models\EmailDelivery;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Str;

/** @extends Factory<EmailDelivery> */
class EmailDeliveryFactory extends Factory
{
    protected $model = EmailDelivery::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['user_id' => User::factory(), 'type' => NotificationType::MediaProcessingFailed->value, 'event_key' => (string) Str::ulid(), 'sent_at' => Date::now()];
    }
}
