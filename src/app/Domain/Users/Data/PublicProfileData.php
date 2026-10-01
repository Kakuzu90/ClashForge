<?php

namespace App\Domain\Users\Data;

use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * What `/u/{username}` shows (FR-PROFILE-5, for the fields that exist today). No email, role,
 * status, ids or privacy settings (specs/11 "Data exposure via page props").
 */
#[TypeScript]
class PublicProfileData extends Data
{
    /**
     * @param  list<CodeLabelData>  $languages
     * @param  list<SocialLinkData>  $socials
     */
    public function __construct(
        public string $username,
        public ?string $displayName,
        public ?string $avatarUrl512,
        public ?string $avatarUrl128,
        public ?string $bio,
        public ?CodeLabelData $country,
        #[DataCollectionOf(CodeLabelData::class)]
        public array $languages,
        #[DataCollectionOf(SocialLinkData::class)]
        public array $socials,
        /** ISO 8601 */
        public string $memberSince,
        public ProfileStatsData $stats,
        public bool $isOwn,
    ) {}
}
