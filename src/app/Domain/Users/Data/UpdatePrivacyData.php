<?php

namespace App\Domain\Users\Data;

use App\Domain\Users\Enums\ProfileVisibility;

/**
 * The privacy fields the settings form may change (FR-PROFILE-4), already validated.
 */
final readonly class UpdatePrivacyData
{
    public function __construct(
        public ProfileVisibility $visibility,
        public bool $showCocAccounts,
        public bool $showClan,
        public bool $allowRecruitmentContact,
        public bool $searchable,
    ) {}
}
