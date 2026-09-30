<?php

namespace App\Http\Data\Settings;

use App\Domain\Media\Data\UploadCollectionData;
use App\Domain\Users\Data\ProfileFormData;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Props for Settings/Profile: the owner's profile, the avatar upload rules, and the choice lists
 * and limits for the form.
 */
#[TypeScript]
class ProfileSettingsPageData extends Data
{
    /**
     * @param  list<array{value: string, label: string}>  $countries
     * @param  list<array{value: string, label: string}>  $languages
     * @param  list<array{value: string, label: string}>  $timezones
     * @param  array{displayNameMax: int, bioMax: int, languagesMax: int}  $limits
     */
    public function __construct(
        public ProfileFormData $profile,
        public UploadCollectionData $avatarUpload,
        public array $countries,
        public array $languages,
        public array $timezones,
        public array $limits,
    ) {}
}
