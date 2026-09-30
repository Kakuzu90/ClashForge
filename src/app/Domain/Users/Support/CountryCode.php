<?php

namespace App\Domain\Users\Support;

use App\Support\ValueObjects\StringValueObject;

/**
 * An ISO-3166-1 alpha-2 country (specs/07 `profiles.country_code`).
 */
final class CountryCode extends StringValueObject
{
    protected static function normalize(string $value): string
    {
        return strtoupper(trim($value));
    }

    protected static function validate(string $value): ?string
    {
        return array_key_exists($value, IsoCodes::countries()) ? null : 'Choose a country from the list.';
    }

    public function name(): string
    {
        return IsoCodes::countries()[$this->value];
    }
}
