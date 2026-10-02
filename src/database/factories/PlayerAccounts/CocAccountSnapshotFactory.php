<?php

namespace Database\Factories\PlayerAccounts;

use App\Domain\PlayerAccounts\Enums\SnapshotSource;
use App\Domain\PlayerAccounts\Models\CocAccount;
use App\Domain\PlayerAccounts\Models\CocAccountSnapshot;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Date;

/**
 * @extends Factory<CocAccountSnapshot>
 */
class CocAccountSnapshotFactory extends Factory
{
    protected $model = CocAccountSnapshot::class;

    /**
     * A scheduled snapshot of a verified account, matching the account factory's numbers.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'coc_account_id' => CocAccount::factory()->verified(),
            'captured_at' => Date::now(),
            'th_level' => 15,
            'xp_level' => 200,
            'trophies' => 4800,
            'best_trophies' => 5100,
            'war_stars' => 900,
            'attack_wins' => 40,
            'defense_wins' => 3,
            'donations' => 1200,
            'heroes' => [],
            'troops' => [],
            'spells' => [],
            'hero_equipment' => [],
            'source' => SnapshotSource::Scheduled,
        ];
    }
}
