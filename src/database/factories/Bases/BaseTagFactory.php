<?php

namespace Database\Factories\Bases;

use App\Domain\Bases\Models\BaseTag;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BaseTag>
 */
class BaseTagFactory extends Factory
{
    protected $model = BaseTag::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = 'tag-'.fake()->unique()->numberBetween(1, 1_000_000);

        return [
            'name' => $name,
            'slug' => $name,
            'usage_count' => 0,
            'is_suggested' => false,
            'is_blocked' => false,
        ];
    }

    public function blocked(): static
    {
        return $this->state(['is_blocked' => true]);
    }
}
