<?php

namespace App\Domain\Users\Services;

use App\Domain\Media\Enums\MediaCollection;
use App\Domain\Media\Services\MediaAttachmentService;
use App\Domain\Users\Data\UpdateProfileData;
use App\Domain\Users\Models\PrivacySettings;
use App\Domain\Users\Models\Profile;
use App\Domain\Users\Models\UserStats;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Profile writes (specs/05 §2 Users). Every user has exactly one profile (FR-PROFILE-1), privacy
 * row and stats row. Each write drops the cached public profile (specs/21 §4).
 */
class ProfileService
{
    public function __construct(private readonly MediaAttachmentService $attachments) {}

    /**
     * Idempotent; registration calls it for each new account (P1-08).
     */
    public function createFor(User $user): void
    {
        DB::transaction(function () use ($user): void {
            if (! Profile::query()->where('user_id', $user->id)->exists()) {
                (new Profile)->forceFill(['user_id' => $user->id])->save();
            }
            if (! PrivacySettings::query()->whereKey($user->id)->exists()) {
                (new PrivacySettings)->forceFill(['user_id' => $user->id])->save();
            }
            if (! UserStats::query()->whereKey($user->id)->exists()) {
                (new UserStats)->forceFill(['user_id' => $user->id])->save();
            }
        });
    }

    public function update(User $user, UpdateProfileData $data): void
    {
        $profile = $this->profileOf($user);
        Gate::forUser($user)->authorize('update', $profile);

        $profile->fill([
            'display_name' => self::plainText($data->displayName),
            'bio' => self::plainText($data->bio),
            'country_code' => $data->countryCode?->value,
            'languages' => $data->languages->codes,
            'timezone' => $data->timezone?->value,
            'socials' => $data->socials->handles,
        ])->save();

        CacheInvalidator::profile($user->username);
    }

    /**
     * One avatar per profile: the new one is attached and the old one released for deletion
     * (specs/10 §8), in one transaction.
     */
    public function setAvatar(User $user, string $mediaUlid): void
    {
        DB::transaction(function () use ($user, $mediaUlid): void {
            $profile = $this->profileOf($user, lock: true);
            Gate::forUser($user)->authorize('update', $profile);

            $previous = $profile->avatar_media_id;
            $mediaId = $this->attachments->attach($user, $mediaUlid, MediaCollection::Avatar, $profile);

            $profile->forceFill(['avatar_media_id' => $mediaId])->save();

            if ($previous !== null && $previous !== $mediaId) {
                $this->attachments->release($previous);
            }
        });

        CacheInvalidator::profile($user->username);
    }

    public function removeAvatar(User $user): void
    {
        DB::transaction(function () use ($user): void {
            $profile = $this->profileOf($user, lock: true);
            Gate::forUser($user)->authorize('update', $profile);

            $previous = $profile->avatar_media_id;

            if ($previous === null) {
                return;
            }

            $profile->forceFill(['avatar_media_id' => null])->save();
            $this->attachments->release($previous);
        });

        CacheInvalidator::profile($user->username);
    }

    private function profileOf(User $user, bool $lock = false): Profile
    {
        $query = Profile::query()->where('user_id', $user->id);

        return ($lock ? $query->lockForUpdate() : $query)->firstOrFail();
    }

    /**
     * FR-PROFILE-6: tags and comments go, everything else stays as typed (`I <3 clash` is fine).
     * Output is escaped on render regardless.
     */
    private static function plainText(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        // Repeat until nothing changes: removing an inner tag can rebuild an outer one
        // (`<<b>script>` → `<script>`).
        do {
            $before = $value;
            $value = (string) preg_replace('/<!--.*?-->|<\/?[a-zA-Z!][^>]*>/s', '', $value);
        } while ($value !== $before);

        $text = trim($value);

        return $text === '' ? null : $text;
    }
}
