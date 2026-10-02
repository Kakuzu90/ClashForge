<?php

namespace App\Domain\Users\Services;

use App\Domain\Media\Enums\VariantName;
use App\Domain\Media\Services\MediaReadService;
use App\Domain\Users\Models\Profile;

/**
 * Avatar URLs for many accounts at once, for other modules' lists (the admin user list).
 */
class AvatarUrlService
{
    public function __construct(private readonly MediaReadService $media) {}

    /**
     * Two queries whatever the count, keyed by user id; accounts without a ready avatar are left out.
     *
     * @param  list<int>  $userIds
     * @return array<int, string>
     */
    public function forUsers(array $userIds, VariantName $variant = VariantName::Thumb): array
    {
        if ($userIds === []) {
            return [];
        }

        /** @var array<int, int> $mediaByUser */
        $mediaByUser = Profile::query()->whereIn('user_id', $userIds)->whereNotNull('avatar_media_id')->pluck('avatar_media_id', 'user_id')->all();
        $urls = $this->media->readyVariantUrls(array_values(array_map('intval', $mediaByUser)), $variant);

        $result = [];
        foreach ($mediaByUser as $userId => $mediaId) {
            if (isset($urls[(int) $mediaId])) {
                $result[(int) $userId] = $urls[(int) $mediaId];
            }
        }

        return $result;
    }
}
