<?php

namespace App\Domain\Users\Services;

use App\Domain\Users\Data\PrivacySettingsData;
use App\Domain\Users\Enums\ProfileVisibility;
use App\Domain\Users\Models\PrivacySettings;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

/**
 * Answers privacy questions for every module (specs/05 §2). Rows come from `user:{id}:privacy`
 * (specs/21 §3) and are memoised for the request (specs/21 L3); the container scopes this class
 * per request. Staff get no bypass (specs/04 §3).
 */
class PrivacyPolicyResolver
{
    /**
     * @var array<int, PrivacySettingsData>
     */
    private array $memo = [];

    public function settingsFor(int $userId): PrivacySettingsData
    {
        return $this->memo[$userId] ??= $this->load($userId);
    }

    /**
     * `public` → anyone; `members` → any signed-in viewer; `private` → the owner only.
     */
    public function canView(?User $viewer, User $owner): bool
    {
        if ($viewer !== null && $viewer->id === $owner->id) {
            return true;
        }

        return match ($this->settingsFor($owner->id)->visibility) {
            ProfileVisibility::Public => true,
            ProfileVisibility::Members => $viewer !== null,
            ProfileVisibility::Private => false,
        };
    }

    /**
     * Search engines may index the profile only when it is `public` and `searchable`.
     */
    public function isIndexable(User $owner): bool
    {
        $settings = $this->settingsFor($owner->id);

        return $settings->visibility === ProfileVisibility::Public && $settings->searchable;
    }

    public function forget(int $userId): void
    {
        unset($this->memo[$userId]);
    }

    /**
     * Writes the current row into the cache (write-through, called by the writer). A reader that
     * loaded the old row just before the change only adds on a miss, so it cannot put it back.
     */
    public function refresh(int $userId): void
    {
        unset($this->memo[$userId]);
        $row = $this->row($userId);

        if ($row === null) {
            CacheInvalidator::privacy($userId);

            return;
        }

        Cache::put(CacheInvalidator::privacyKey($userId), $row, (int) config('platform.profile.privacy_cache_ttl'));
    }

    private function load(int $userId): PrivacySettingsData
    {
        $key = CacheInvalidator::privacyKey($userId);
        $row = Cache::get($key);

        if (! is_array($row)) {
            $row = $this->row($userId);

            if ($row !== null) {
                Cache::add($key, $row, (int) config('platform.profile.privacy_cache_ttl'));
            }
        }

        return is_array($row) ? PrivacySettingsData::fromRow($row) : PrivacySettingsData::closed();
    }

    /**
     * The row as scalars, so a changed enum or DTO never fails to unserialise.
     *
     * @return array<string, string|bool>|null
     */
    private function row(int $userId): ?array
    {
        $settings = PrivacySettings::query()->whereKey($userId)->first();

        return $settings === null ? null : [
            'profile_visibility' => $settings->profile_visibility->value,
            'show_coc_accounts' => $settings->show_coc_accounts,
            'show_clan' => $settings->show_clan,
            'show_activity' => $settings->show_activity,
            'allow_recruitment_contact' => $settings->allow_recruitment_contact,
            'allow_marketplace_contact' => $settings->allow_marketplace_contact,
            'searchable' => $settings->searchable,
        ];
    }
}
