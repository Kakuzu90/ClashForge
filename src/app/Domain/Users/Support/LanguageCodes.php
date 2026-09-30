<?php

namespace App\Domain\Users\Support;

use InvalidArgumentException;

/**
 * Up to `platform.profile.languages_max` distinct ISO-639-1 languages, in the order chosen
 * (specs/07 `profiles.languages`).
 */
final readonly class LanguageCodes
{
    /**
     * @param  list<string>  $codes
     */
    private function __construct(public array $codes) {}

    /**
     * @param  array<mixed>  $codes
     */
    public static function from(array $codes): self
    {
        if (($error = self::errorFor($codes)) !== null) {
            throw new InvalidArgumentException($error);
        }

        return new self(self::normalize($codes));
    }

    /**
     * @param  array<mixed>  $codes
     */
    public static function errorFor(array $codes): ?string
    {
        foreach ($codes as $code) {
            if (! is_string($code) || ! array_key_exists(strtolower(trim($code)), IsoCodes::languages())) {
                return 'Choose languages from the list.';
            }
        }

        $max = (int) config('platform.profile.languages_max');

        return count(self::normalize($codes)) > $max ? "Choose up to {$max} languages." : null;
    }

    /**
     * @param  array<mixed>  $codes
     * @return list<string>
     */
    private static function normalize(array $codes): array
    {
        return array_values(array_unique(array_map(fn (mixed $code): string => strtolower(trim((string) $code)), $codes)));
    }
}
