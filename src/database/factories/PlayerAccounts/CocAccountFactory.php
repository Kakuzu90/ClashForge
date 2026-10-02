<?php

namespace Database\Factories\PlayerAccounts;

use App\Domain\CocIntegration\Data\CocTag;
use App\Domain\PlayerAccounts\Enums\CocAccountStatus;
use App\Domain\PlayerAccounts\Enums\VerificationMethod;
use App\Domain\PlayerAccounts\Models\CocAccount;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Date;

/**
 * @extends Factory<CocAccount>
 */
class CocAccountFactory extends Factory
{
    protected $model = CocAccount::class;

    /**
     * An attached, not yet verified account with a random valid tag.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $bare = '';
        foreach (range(1, 8) as $i) {
            $bare .= CocTag::ALPHABET[random_int(0, strlen(CocTag::ALPHABET) - 1)];
        }

        return [
            'user_id' => User::factory(),
            'tag' => '#'.$bare,
            'tag_normalized' => $bare,
            'status' => CocAccountStatus::Unverified,
            'ign' => 'Factory Chief',
            'th_level' => 15,
            'xp_level' => 200,
            'trophies' => 4800,
            'best_trophies' => 5100,
            'war_stars' => 900,
            'attack_wins' => 40,
            'defense_wins' => 3,
            'donations' => 1200,
            'api_synced_at' => Date::now(),
        ];
    }

    public function forTag(string $tag): static
    {
        $bare = ltrim($tag, '#');

        return $this->state(['tag' => '#'.$bare, 'tag_normalized' => $bare]);
    }

    public function verified(): static
    {
        return $this->state([
            'status' => CocAccountStatus::Verified,
            'verified_at' => Date::now(),
            'verification_method' => VerificationMethod::ApiToken,
        ]);
    }

    public function featured(): static
    {
        return $this->state(['is_featured' => true]);
    }

    public function disputed(): static
    {
        return $this->verified()->state(['status' => CocAccountStatus::Disputed]);
    }

    public function released(): static
    {
        return $this->state(['user_id' => null, 'status' => CocAccountStatus::Released]);
    }
}
