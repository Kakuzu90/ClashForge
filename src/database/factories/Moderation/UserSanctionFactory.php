<?php

namespace Database\Factories\Moderation;

use App\Domain\Moderation\Enums\ReasonCode;
use App\Domain\Moderation\Enums\SanctionType;
use App\Domain\Moderation\Models\ModerationAction;
use App\Domain\Moderation\Models\UserSanction;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserSanction>
 */
class UserSanctionFactory extends Factory
{
    protected $model = UserSanction::class;

    /**
     * An active seven-day suspension with its moderation action. The account's own status is
     * not touched; use SanctionService when the test needs both.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'type' => SanctionType::Suspension,
            'reason_code' => ReasonCode::Harassment,
            'public_reason' => 'Harassment in comments',
            'internal_note' => 'Repeated insults after a warning.',
            'issued_by' => User::factory()->admin(),
            'starts_at' => now(),
            'expires_at' => now()->addDays(7),
            'moderation_action_id' => fn (array $attributes) => ModerationAction::factory()->create([
                'actor_id' => $attributes['issued_by'],
                'target_id' => $attributes['user_id'],
                'target_user_id' => $attributes['user_id'],
            ])->id,
        ];
    }

    public function ban(): static
    {
        return $this->state(['type' => SanctionType::Ban, 'expires_at' => null, 'public_reason' => 'Account trading']);
    }
}
