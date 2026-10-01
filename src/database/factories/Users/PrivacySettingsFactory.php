<?php

namespace Database\Factories\Users;

use App\Domain\Users\Enums\ProfileVisibility;
use App\Domain\Users\Models\PrivacySettings;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * @extends Factory<PrivacySettings>
 */
class PrivacySettingsFactory extends Factory
{
    protected $model = PrivacySettings::class;

    /**
     * The defaults a new account gets (specs/07 `privacy_settings`).
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'profile_visibility' => ProfileVisibility::Public,
            'show_coc_accounts' => true,
            'show_clan' => true,
            'show_activity' => true,
            'allow_recruitment_contact' => true,
            'allow_marketplace_contact' => false,
            'searchable' => true,
        ];
    }

    /**
     * A user already has its row (UserFactory creates it, as registration does), so this fills
     * that row instead of inserting a second one.
     *
     * @param  Collection<int, Model>  $results
     */
    protected function store(Collection $results): void
    {
        $results->each(function (Model $settings): void {
            $settings->exists = PrivacySettings::query()->whereKey($settings->getAttribute('user_id'))->exists();
            $settings->save();
        });
    }

    public function members(): static
    {
        return $this->state(['profile_visibility' => ProfileVisibility::Members]);
    }

    public function private(): static
    {
        return $this->state(['profile_visibility' => ProfileVisibility::Private]);
    }

    public function unsearchable(): static
    {
        return $this->state(['searchable' => false]);
    }
}
