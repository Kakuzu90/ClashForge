<?php

namespace Database\Factories\Clans;

use App\Domain\Clans\Models\Clan;
use App\Domain\CocIntegration\Data\CocTag;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Clan>
 */
class ClanFactory extends Factory
{
    protected $model = Clan::class;

    /**
     * A stub as a member's player payload leaves it: tag, name, badge and level, nothing synced.
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
            'tag' => '#'.$bare,
            'tag_normalized' => $bare,
            'name' => 'Factory Clan',
            'badge_urls' => [
                'small' => "https://api-assets.clashofclans.com/badges/70/{$bare}.png",
                'medium' => "https://api-assets.clashofclans.com/badges/200/{$bare}.png",
            ],
            'level' => 10,
        ];
    }

    public function forTag(string $tag): static
    {
        $bare = ltrim($tag, '#');

        return $this->state(['tag' => '#'.$bare, 'tag_normalized' => $bare]);
    }

    public function withoutBadge(): static
    {
        return $this->state(['badge_urls' => []]);
    }
}
