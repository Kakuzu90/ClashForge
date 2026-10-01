<?php

namespace Database\Factories\Notifications;

use App\Domain\Notifications\Enums\NotificationType;
use App\Domain\Notifications\Models\Notification;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Date;

/**
 * @extends Factory<Notification>
 */
class NotificationFactory extends Factory
{
    protected $model = Notification::class;

    /**
     * An unread "password changed" notice for a fresh account.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'type' => NotificationType::PasswordChanged->value,
            'notifiable_type' => User::class,
            'notifiable_id' => User::factory(),
            'data' => ['params' => []],
            'group_key' => null,
        ];
    }

    public function forUser(User $user): static
    {
        return $this->state(fn () => ['notifiable_type' => User::class, 'notifiable_id' => $user->id]);
    }

    /**
     * @param  array<string, string|int|bool|null>  $params
     */
    public function ofType(NotificationType $type, array $params = []): static
    {
        return $this->state(fn () => ['type' => $type->value, 'data' => ['params' => $params]]);
    }

    public function read(mixed $at = null): static
    {
        return $this->state(fn () => ['read_at' => $at ?? Date::now()]);
    }

    public function createdAt(mixed $when): static
    {
        return $this->state(fn () => ['created_at' => $when, 'updated_at' => $when]);
    }
}
