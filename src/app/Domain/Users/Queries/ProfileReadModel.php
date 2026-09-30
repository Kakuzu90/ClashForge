<?php

namespace App\Domain\Users\Queries;

use App\Domain\Media\Enums\MediaStatus;
use App\Domain\Media\Enums\VariantName;
use App\Domain\Media\Services\MediaReadService;
use App\Domain\Users\Data\AvatarData;
use App\Domain\Users\Data\ProfileFormData;
use App\Domain\Users\Data\SocialHandlesData;
use App\Domain\Users\Models\Profile;
use App\Models\User;

class ProfileReadModel
{
    public function __construct(private readonly MediaReadService $media) {}

    public function forOwner(User $user): ProfileFormData
    {
        $profile = Profile::query()->where('user_id', $user->id)->firstOrFail();
        $socials = $profile->socials;

        return new ProfileFormData(
            username: $user->username,
            displayName: $profile->display_name,
            bio: $profile->bio,
            countryCode: $profile->country_code,
            languages: $profile->languages,
            timezone: $profile->timezone,
            socials: new SocialHandlesData(
                youtube: $socials['youtube'] ?? null,
                twitch: $socials['twitch'] ?? null,
                x: $socials['x'] ?? null,
                discord: $socials['discord'] ?? null,
            ),
            avatar: $this->avatar($profile->avatar_media_id),
        );
    }

    /**
     * The small avatar for the header (shared props), or null while there is none or it is still
     * processing.
     */
    public function avatarUrl(User $user, VariantName $variant = VariantName::Thumb): ?string
    {
        $mediaId = Profile::query()->where('user_id', $user->id)->value('avatar_media_id');

        if ($mediaId === null) {
            return null;
        }

        return ($this->media->readyVariants((int) $mediaId)[$variant->value] ?? null)?->url;
    }

    private function avatar(?int $mediaId): AvatarData
    {
        if ($mediaId === null) {
            return new AvatarData(status: null, url512: null, url128: null, url48: null);
        }

        $variants = $this->media->readyVariants($mediaId);

        return new AvatarData(
            status: $variants === [] ? $this->media->status($mediaId) : MediaStatus::Ready,
            url512: ($variants[VariantName::Full->value] ?? null)?->url,
            url128: ($variants[VariantName::Card->value] ?? null)?->url,
            url48: ($variants[VariantName::Thumb->value] ?? null)?->url,
        );
    }
}
