<?php

namespace App\Domain\Users\Data;

use App\Domain\Users\Enums\ProfileVisibility;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * The owner's privacy choices, as the settings form edits them. `show_activity` and
 * `allow_marketplace_contact` are stored but stay off the form until P2 / Phase 6.
 */
#[TypeScript]
class PrivacyFormData extends Data
{
    public function __construct(
        public ProfileVisibility $visibility,
        public bool $showCocAccounts,
        public bool $showClan,
        public bool $allowRecruitmentContact,
        public bool $searchable,
    ) {}

    public static function fromSettings(PrivacySettingsData $settings): self
    {
        return new self(
            visibility: $settings->visibility,
            showCocAccounts: $settings->showCocAccounts,
            showClan: $settings->showClan,
            allowRecruitmentContact: $settings->allowRecruitmentContact,
            searchable: $settings->searchable,
        );
    }
}
