<?php

namespace Database\Factories\Auth;

use App\Domain\Auth\Models\UsernameHistory;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<UsernameHistory> */
class UsernameHistoryFactory extends Factory
{
    protected $model = UsernameHistory::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'username' => fake()->unique()->regexify('[a-z][a-z0-9_]{5,14}'),
            'released_at' => now(),
            'reserved_forever' => false,
        ];
    }

    public function permanent(): static
    {
        return $this->state(['reserved_forever' => true]);
    }
}
