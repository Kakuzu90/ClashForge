<?php

namespace App\Domain\Users\Data;

use App\Domain\Users\Support\CountryCode;
use App\Domain\Users\Support\LanguageCodes;
use App\Domain\Users\Support\SocialLinks;
use App\Domain\Users\Support\Timezone;

/**
 * The editable profile fields (FR-PROFILE-2), already validated into value objects.
 */
final readonly class UpdateProfileData
{
    public function __construct(
        public ?string $displayName,
        public ?string $bio,
        public ?CountryCode $countryCode,
        public LanguageCodes $languages,
        public ?Timezone $timezone,
        public SocialLinks $socials,
    ) {}

    /**
     * From input the form request has already validated with ProfileFieldRules.
     *
     * @param  array<mixed>  $languages
     * @param  array<string, mixed>  $socials
     */
    public static function fromValidated(?string $displayName, ?string $bio, ?string $countryCode, array $languages, ?string $timezone, array $socials): self
    {
        return new self(
            displayName: $displayName,
            bio: $bio,
            countryCode: $countryCode === null ? null : CountryCode::from($countryCode),
            languages: LanguageCodes::from($languages),
            timezone: $timezone === null ? null : Timezone::from($timezone),
            socials: SocialLinks::from($socials),
        );
    }
}
