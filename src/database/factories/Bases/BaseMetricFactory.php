<?php

namespace Database\Factories\Bases;

use App\Domain\Bases\Models\BaseLayout;
use App\Domain\Bases\Models\BaseMetric;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BaseMetric>
 */
class BaseMetricFactory extends Factory
{
    protected $model = BaseMetric::class;

    /**
     * Zero counters for a new base.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return ['base_layout_id' => BaseLayout::factory()];
    }
}
