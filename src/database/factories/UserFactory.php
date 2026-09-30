<?php

namespace Database\Factories;

use App\Domain\Auth\Enums\Role;
use App\Domain\Auth\Enums\UserStatus;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'ulid' => Str::lower((string) Str::ulid()),
            // specs/07: 3–20 chars of [a-z0-9_].
            'username' => fake()->unique()->regexify('[a-z][a-z0-9_]{5,14}'),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'role' => Role::User,
            'status' => UserStatus::Active,
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    public function moderator(): static
    {
        return $this->state(['role' => Role::Moderator]);
    }

    public function admin(): static
    {
        return $this->state(['role' => Role::Admin]);
    }

    public function superAdmin(): static
    {
        return $this->state(['role' => Role::SuperAdmin]);
    }

    public function restricted(?CarbonInterface $until = null, string $reason = 'Spam in comments'): static
    {
        return $this->withStatus(UserStatus::Restricted, $reason, $until ?? now()->addDays(3));
    }

    public function suspended(?CarbonInterface $until = null, string $reason = 'Harassment'): static
    {
        return $this->withStatus(UserStatus::Suspended, $reason, $until ?? now()->addDays(14));
    }

    public function banned(string $reason = 'Account trading'): static
    {
        return $this->withStatus(UserStatus::Banned, $reason, null);
    }

    public function pendingDeletion(): static
    {
        return $this->withStatus(UserStatus::PendingDeletion, null, null);
    }

    private function withStatus(UserStatus $status, ?string $reason, ?CarbonInterface $until): static
    {
        return $this->state([
            'status' => $status,
            'status_reason' => $reason,
            'status_expires_at' => $until,
        ]);
    }
}
