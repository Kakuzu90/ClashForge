<?php

namespace App\Domain\Notifications\Services;

use App\Domain\Notifications\Data\EmailCategoryData;
use App\Domain\Notifications\Data\EmailPreferencesData;
use App\Domain\Notifications\Data\UpdateEmailPreferencesData;
use App\Domain\Notifications\Enums\NotificationCategory;
use App\Domain\Notifications\Models\NotificationPreference;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class EmailPreferenceService
{
    public function read(User $user): EmailPreferencesData
    {
        $settings = $this->settings($user);
        Gate::forUser($user)->authorize('view', $settings);

        return new EmailPreferencesData(
            $settings->non_security_email_enabled,
            array_map(fn (NotificationCategory $category): EmailCategoryData => new EmailCategoryData(
                $category->value, $category->label(), $this->categoryEnabled($settings, $category), $category === NotificationCategory::Security,
            ), NotificationCategory::cases()),
            Gate::forUser($user)->allows('update', $settings),
        );
    }

    public function update(User $user, UpdateEmailPreferencesData $data): void
    {
        DB::transaction(function () use ($user, $data): void {
            $owner = User::query()->lockForUpdate()->findOrFail($user->id);
            $settings = $this->settings($owner);
            Gate::forUser($owner)->authorize('update', $settings);
            $channels = $this->channels($settings);
            foreach (NotificationCategory::cases() as $category) {
                if ($category !== NotificationCategory::Security && array_key_exists($category->value, $data->emailCategories)) {
                    $channels[$category->value]['email'] = $data->emailCategories[$category->value];
                }
            }
            $settings->fill(['channel_prefs' => $channels, 'non_security_email_enabled' => $data->emailEnabled])->save();
        });
    }

    public function settings(User $user): NotificationPreference
    {
        return NotificationPreference::query()->whereKey($user->id)->first() ?? (new NotificationPreference)->forceFill(['user_id' => $user->id]);
    }

    public function allowsEmail(User $user, NotificationCategory $category): bool
    {
        if ($category === NotificationCategory::Security) {
            return true;
        }

        $settings = $this->settings($user);

        return $settings->non_security_email_enabled && $this->categoryEnabled($settings, $category);
    }

    private function categoryEnabled(NotificationPreference $settings, NotificationCategory $category): bool
    {
        return $category === NotificationCategory::Security || ($settings->channel_prefs[$category->value]['email'] ?? $category->emailDefault());
    }

    /** @return array<string, array{in_app: bool, email: bool}> */
    private function channels(NotificationPreference $settings): array
    {
        $channels = [];
        foreach (NotificationCategory::cases() as $category) {
            $channels[$category->value] = [
                'in_app' => $category === NotificationCategory::Security || ($settings->channel_prefs[$category->value]['in_app'] ?? true),
                'email' => $this->categoryEnabled($settings, $category),
            ];
        }

        return $channels;
    }
}
