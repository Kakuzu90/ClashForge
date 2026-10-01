<?php

namespace App\Domain\Users\Data;

use App\Domain\Users\Enums\ProfileVisibility;

/**
 * A user's privacy row, as PrivacyPolicyResolver serves it to every module (specs/05 §2). Never a
 * page prop for anyone but the owner.
 */
final readonly class PrivacySettingsData
{
    public function __construct(
        public ProfileVisibility $visibility,
        public bool $showCocAccounts,
        public bool $showClan,
        public bool $showActivity,
        public bool $allowRecruitmentContact,
        public bool $allowMarketplaceContact,
        public bool $searchable,
    ) {}

    /**
     * Shown to nobody: the fallback if an account has no row, so a gap never opens a profile.
     */
    public static function closed(): self
    {
        return new self(ProfileVisibility::Private, false, false, false, false, false, false);
    }

    /**
     * @param  array<string, mixed>  $row  the cached `privacy_settings` columns
     */
    public static function fromRow(array $row): self
    {
        return new self(
            visibility: ProfileVisibility::from((string) $row['profile_visibility']),
            showCocAccounts: (bool) $row['show_coc_accounts'],
            showClan: (bool) $row['show_clan'],
            showActivity: (bool) $row['show_activity'],
            allowRecruitmentContact: (bool) $row['allow_recruitment_contact'],
            allowMarketplaceContact: (bool) $row['allow_marketplace_contact'],
            searchable: (bool) $row['searchable'],
        );
    }
}
