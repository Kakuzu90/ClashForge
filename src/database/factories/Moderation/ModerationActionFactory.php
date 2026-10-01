<?php

namespace Database\Factories\Moderation;

use App\Domain\Moderation\Enums\ModerationActionType;
use App\Domain\Moderation\Enums\ReasonCode;
use App\Domain\Moderation\Models\ModerationAction;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ModerationAction>
 */
class ModerationActionFactory extends Factory
{
    protected $model = ModerationAction::class;

    /**
     * An admin suspending a user for seven days.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $target = User::factory();

        return [
            'actor_id' => User::factory()->admin(),
            'action' => ModerationActionType::Suspend,
            'target_type' => ModerationAction::TARGET_USER,
            'target_id' => $target,
            'target_user_id' => fn (array $attributes) => $attributes['target_id'],
            'reason_code' => ReasonCode::Harassment,
            'note' => 'Repeated insults in comments after a warning.',
            'duration_hours' => 7 * 24,
            'metadata' => [],
            'ip_hash' => null,
        ];
    }
}
