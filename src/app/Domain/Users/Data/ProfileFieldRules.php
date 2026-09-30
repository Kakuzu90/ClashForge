<?php

namespace App\Domain\Users\Data;

use App\Domain\Users\Support\CountryCode;
use App\Domain\Users\Support\LanguageCodes;
use App\Domain\Users\Support\SocialLinks;
use App\Domain\Users\Support\Timezone;

/**
 * The profile value objects' rules, for the Http form request, which may not reach module
 * internals (specs/19 §2). Each returns the error message, or null when the value is valid.
 */
final class ProfileFieldRules
{
    /**
     * @return list<string>
     */
    public static function socialNetworks(): array
    {
        return SocialLinks::networks();
    }

    public static function countryError(string $value): ?string
    {
        return CountryCode::errorFor($value);
    }

    public static function timezoneError(string $value): ?string
    {
        return Timezone::errorFor($value);
    }

    /**
     * @param  array<mixed>  $codes
     */
    public static function languagesError(array $codes): ?string
    {
        return LanguageCodes::errorFor($codes);
    }

    public static function socialError(string $network, mixed $value): ?string
    {
        return SocialLinks::errorFor($network, $value);
    }
}
