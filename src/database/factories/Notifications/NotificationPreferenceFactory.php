<?php

namespace Database\Factories\Notifications;

use App\Domain\Notifications\Models\NotificationPreference;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<NotificationPreference> */
class NotificationPreferenceFactory extends Factory
{
    protected $model = NotificationPreference::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['user_id' => User::factory(), 'channel_prefs' => [], 'non_security_email_enabled' => true];
    }

    public function unsubscribed(): static
    {
        return $this->state(['non_security_email_enabled' => false]);
    }
}
