<?php

namespace App\Http\Data\Settings;

use App\Domain\Users\Data\PrivacyFormData;
use App\Domain\Users\Data\VisibilityOptionData;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Props for Settings/Privacy: the owner's choices, the visibility options and the owner's own
 * profile link.
 */
#[TypeScript]
class PrivacySettingsPageData extends Data
{
    /**
     * @param  list<VisibilityOptionData>  $visibilityOptions
     */
    public function __construct(
        public PrivacyFormData $settings,
        #[DataCollectionOf(VisibilityOptionData::class)]
        public array $visibilityOptions,
        public string $username,
    ) {}
}
