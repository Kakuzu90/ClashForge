<?php

namespace Database\Factories\Bases;

use App\Domain\Bases\Enums\BaseCategory;
use App\Domain\Bases\Enums\BaseModerationState;
use App\Domain\Bases\Enums\BaseStatus;
use App\Domain\Bases\Enums\BaseVisibility;
use App\Domain\Bases\Models\BaseLayout;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Str;

/**
 * @extends Factory<BaseLayout>
 */
class BaseLayoutFactory extends Factory
{
    protected $model = BaseLayout::class;

    /**
     * A published public war base with a valid link of its own.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $ulid = (string) Str::ulid();
        $layoutId = 'TH16:WB:'.Str::random(24);

        return [
            'ulid' => $ulid,
            'slug' => "{$ulid}-factory-base",
            'user_id' => User::factory(),
            'coc_account_id' => null,
            'title' => 'Factory base',
            'description' => null,
            'th_level' => 16,
            'category' => BaseCategory::War,
            'base_link' => 'https://link.clashofclans.com/en?action=OpenLayout&id='.rawurlencode($layoutId),
            'layout_hash' => hash('sha256', $layoutId),
            'visibility' => BaseVisibility::Public,
            'status' => BaseStatus::Published,
            'has_video' => false,
            'published_at' => Date::now(),
            'moderation_state' => BaseModerationState::Clean,
            'flagged_reason' => null,
        ];
    }

    public function processing(): static
    {
        return $this->state(['status' => BaseStatus::Processing, 'published_at' => null]);
    }

    /**
     * A base for this in-game layout id, hashed as publishing hashes it.
     */
    public function forLayout(string $layoutId): static
    {
        return $this->state(['layout_hash' => hash('sha256', $layoutId), 'base_link' => 'https://link.clashofclans.com/en?action=OpenLayout&id='.rawurlencode($layoutId)]);
    }
}
