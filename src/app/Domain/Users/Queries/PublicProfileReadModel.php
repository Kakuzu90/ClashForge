<?php

namespace App\Domain\Users\Queries;

use App\Domain\Auth\Services\UserLookupService;
use App\Domain\Media\Enums\VariantName;
use App\Domain\Media\Services\MediaReadService;
use App\Domain\Users\Data\CodeLabelData;
use App\Domain\Users\Data\ProfileStatsData;
use App\Domain\Users\Data\PublicProfileData;
use App\Domain\Users\Data\PublicProfileViewData;
use App\Domain\Users\Data\SocialLinkData;
use App\Domain\Users\Models\Profile;
use App\Domain\Users\Models\UserStats;
use App\Domain\Users\Services\CacheInvalidator;
use App\Domain\Users\Services\PrivacyPolicyResolver;
use App\Domain\Users\Support\IsoCodes;
use App\Domain\Users\Support\SocialLinks;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

/**
 * The public profile at `/u/{username}` (FR-PROFILE-5). Who may see it is decided on every
 * request before the cached view-model (`profile:{username}`, specs/21 §3) is read, so a privacy
 * change or a ban applies at once.
 */
class PublicProfileReadModel
{
    public function __construct(
        private readonly UserLookupService $users,
        private readonly PrivacyPolicyResolver $privacy,
        private readonly MediaReadService $media,
    ) {}

    /**
     * Null for an unknown username, a profile hidden from this viewer, and a banned or
     * pending-deletion owner alike (specs/11 "Account enumeration").
     */
    public function find(string $username, ?User $viewer): ?PublicProfileViewData
    {
        $owner = $this->users->findListed($username);

        if ($owner === null || ! $this->privacy->canView($viewer, $owner)) {
            return null;
        }

        $cached = $this->cached($owner);

        return new PublicProfileViewData(
            profile: PublicProfileData::from([...$cached, 'isOwn' => $viewer !== null && $viewer->id === $owner->id]),
            indexable: $this->privacy->isIndexable($owner),
        );
    }

    /**
     * The current username to send `/u/{old}` to after a change (FR-PROFILE-7), only when that
     * profile would render for this viewer; otherwise null, so the old URL gets the same 404 and
     * never links a hidden account to its old name (specs/11 "Account enumeration").
     */
    public function redirectFor(string $username, ?User $viewer): ?string
    {
        $owner = $this->users->renamedFrom($username);

        return $owner !== null && $this->privacy->canView($viewer, $owner) ? $owner->username : null;
    }

    /**
     * `profile:{username}`, accepted only when it was built under the current version, so a stale
     * entry written after an invalidation is rebuilt instead of served.
     *
     * @return array<string, mixed>
     */
    private function cached(User $owner): array
    {
        $key = CacheInvalidator::profileKey($owner->username);
        $version = CacheInvalidator::profileVersion($owner->username);
        $entry = Cache::get($key);

        if (is_array($entry) && ($entry['version'] ?? null) === $version && is_array($entry['data'] ?? null)) {
            return $entry['data'];
        }

        $data = $this->build($owner);
        Cache::put($key, ['version' => $version, 'data' => $data], (int) config('platform.profile.cache_ttl'));

        return $data;
    }

    /**
     * The viewer-independent part, as scalars and arrays so the cache entry outlives DTO changes.
     *
     * @return array<string, mixed>
     */
    private function build(User $owner): array
    {
        $profile = Profile::query()->where('user_id', $owner->id)->firstOrFail();
        $stats = UserStats::query()->whereKey($owner->id)->first();
        $variants = $profile->avatar_media_id === null ? [] : $this->media->readyVariants($profile->avatar_media_id);

        $countries = IsoCodes::countries();
        $languages = IsoCodes::languages();

        $socials = [];
        foreach (SocialLinks::from($profile->socials)->links() as $network => $link) {
            $socials[] = (new SocialLinkData($network, SocialLinks::label($network), $link['handle'], $link['url']))->toArray();
        }

        return [
            'username' => $owner->username,
            'displayName' => $profile->display_name,
            'avatarUrl512' => ($variants[VariantName::Full->value] ?? null)?->url,
            'avatarUrl128' => ($variants[VariantName::Card->value] ?? null)?->url,
            'bio' => $profile->bio,
            'country' => $profile->country_code === null ? null
                : (new CodeLabelData($profile->country_code, $countries[$profile->country_code] ?? $profile->country_code))->toArray(),
            'languages' => array_map(
                fn (string $code): array => (new CodeLabelData($code, $languages[$code] ?? $code))->toArray(),
                $profile->languages,
            ),
            'socials' => $socials,
            'memberSince' => $owner->created_at->toIso8601String(),
            'stats' => (new ProfileStatsData(
                basesPublished: $stats->bases_published ?? 0,
                likesReceived: $stats->total_base_likes ?? 0,
                copies: $stats->total_base_copies ?? 0,
            ))->toArray(),
        ];
    }
}
