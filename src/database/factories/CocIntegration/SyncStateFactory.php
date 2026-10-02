<?php

namespace Database\Factories\CocIntegration;

use App\Domain\CocIntegration\Enums\SyncResourceType;
use App\Domain\CocIntegration\Enums\SyncTier;
use App\Domain\CocIntegration\Models\SyncState;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Date;

/**
 * @extends Factory<SyncState>
 */
class SyncStateFactory extends Factory
{
    protected $model = SyncState::class;

    /**
     * A healthy cold account row, due now.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'resource_type' => SyncResourceType::CocAccount,
            'resource_id' => fake()->unique()->numberBetween(1, 1_000_000),
            'last_attempt_at' => null,
            'last_success_at' => null,
            'consecutive_failures' => 0,
            'frozen_attempts' => 0,
            'next_due_at' => Date::now(),
            'tier' => SyncTier::Cold,
        ];
    }

    public function forAccount(int $accountId): static
    {
        return $this->state(['resource_type' => SyncResourceType::CocAccount, 'resource_id' => $accountId]);
    }

    public function frozen(int $attempts = 0): static
    {
        return $this->state(['tier' => SyncTier::Frozen, 'consecutive_failures' => 5, 'frozen_attempts' => $attempts]);
    }

    public function stopped(): static
    {
        return $this->frozen((int) config('coc.sync.frozen_max_attempts'))->state(['next_due_at' => null]);
    }
}
