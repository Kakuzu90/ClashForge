<?php

namespace App\Domain\Users\Services;

use App\Domain\Media\Enums\VariantName;
use App\Domain\Media\Services\MediaReadService;
use App\Domain\Users\Data\AuthorData;
use App\Domain\Users\Enums\ProfileVisibility;
use App\Models\User;

/**
 * Authors of public content for other modules' lists, in two queries whatever the count. Everyone
 * sees the username, which the content needs for attribution; the display name and avatar only
 * when the profile is public, and `accountsPublic` says whether the author also shows their CoC
 * accounts to everyone, so a card can drop a credited account the author keeps private. No privacy
 * row reads as private (specs/21 §3).
 */
class AuthorDirectory
{
    public function __construct(private readonly MediaReadService $media) {}

    /**
     * @param  list<int>  $userIds
     * @return array<int, array{author: AuthorData, accountsPublic: bool}> keyed by user id
     */
    public function forUsers(array $userIds): array
    {
        if ($userIds === []) {
            return [];
        }

        $rows = User::query()
            ->leftJoin('profiles', 'profiles.user_id', '=', 'users.id')
            ->leftJoin('privacy_settings', 'privacy_settings.user_id', '=', 'users.id')
            ->whereIn('users.id', array_values(array_unique($userIds)))
            ->toBase()
            ->get(['users.id', 'users.username', 'profiles.display_name', 'profiles.avatar_media_id', 'privacy_settings.profile_visibility', 'privacy_settings.show_coc_accounts']);

        $mediaIds = [];
        foreach ($rows as $row) {
            if ($row->avatar_media_id !== null) {
                $mediaIds[] = (int) $row->avatar_media_id;
            }
        }
        $avatars = $this->media->readyVariantUrls($mediaIds, VariantName::Thumb);

        $result = [];
        foreach ($rows as $row) {
            $public = $row->profile_visibility === ProfileVisibility::Public->value;
            $result[(int) $row->id] = [
                'author' => new AuthorData(
                    username: (string) $row->username,
                    displayName: ! $public || $row->display_name === null ? null : (string) $row->display_name,
                    avatarUrl: ! $public || $row->avatar_media_id === null ? null : ($avatars[(int) $row->avatar_media_id] ?? null),
                ),
                'accountsPublic' => $public && (bool) $row->show_coc_accounts,
            ];
        }

        return $result;
    }
}
