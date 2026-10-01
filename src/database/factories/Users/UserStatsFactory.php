<?php

namespace Database\Factories\Users;

use App\Domain\Users\Models\UserStats;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * @extends Factory<UserStats>
 */
class UserStatsFactory extends Factory
{
    protected $model = UserStats::class;

    /**
     * Zero counters, as a new account starts.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'bases_published' => 0,
            'total_base_likes' => 0,
            'total_base_copies' => 0,
            'total_base_views' => 0,
            'comments_posted' => 0,
            'recomputed_at' => null,
        ];
    }

    /**
     * Fills the row UserFactory created with the user instead of inserting a second one.
     *
     * @param  Collection<int, Model>  $results
     */
    protected function store(Collection $results): void
    {
        $results->each(function (Model $stats): void {
            $stats->exists = UserStats::query()->whereKey($stats->getAttribute('user_id'))->exists();
            $stats->save();
        });
    }

    public function active(): static
    {
        return $this->state([
            'bases_published' => 12,
            'total_base_likes' => 3400,
            'total_base_copies' => 980,
            'total_base_views' => 15200,
            'comments_posted' => 41,
            'recomputed_at' => now(),
        ]);
    }
}
