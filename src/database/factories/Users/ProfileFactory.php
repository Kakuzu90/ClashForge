<?php

namespace Database\Factories\Users;

use App\Domain\Users\Models\Profile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * @extends Factory<Profile>
 */
class ProfileFactory extends Factory
{
    protected $model = Profile::class;

    /**
     * An empty profile, as registration creates it.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'display_name' => null,
            'bio' => null,
            'country_code' => null,
            'languages' => [],
            'timezone' => null,
            'socials' => [],
        ];
    }

    /**
     * A user already has its profile (UserFactory creates it, as registration does), so this
     * fills that row instead of inserting a second one.
     *
     * @param  Collection<int, Model>  $results
     */
    protected function store(Collection $results): void
    {
        $results->each(function (Model $profile): void {
            $existing = Profile::query()->where('user_id', $profile->getAttribute('user_id'))->value('id');

            if ($existing !== null) {
                $profile->setAttribute('id', $existing);
                $profile->exists = true;
            }

            $profile->save();
        });
    }

    public function filled(): static
    {
        return $this->state([
            'display_name' => fake()->name(),
            'bio' => fake()->sentence(12),
            'country_code' => 'DE',
            'languages' => ['de', 'en'],
            'timezone' => 'Europe/Berlin',
            'socials' => ['youtube' => '@clashchief', 'discord' => 'clashchief'],
        ]);
    }
}
