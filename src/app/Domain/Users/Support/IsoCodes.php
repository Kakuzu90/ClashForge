<?php

namespace App\Domain\Users\Support;

use ResourceBundle;

/**
 * ISO-3166-1 alpha-2 countries and ISO-639-1 languages, with English names, from the ICU data in
 * ext-intl. ICU also lists regions that are not countries (EU, UN, XK, …); those are left out.
 */
final class IsoCodes
{
    private const NOT_COUNTRIES = ['AC', 'CP', 'CQ', 'DG', 'EA', 'EU', 'EZ', 'IC', 'QO', 'TA', 'UN', 'XA', 'XB', 'XK', 'ZZ'];

    /** @var array<string, string>|null */
    private static ?array $countries = null;

    /** @var array<string, string>|null */
    private static ?array $languages = null;

    /**
     * @return array<string, string> code → English name, sorted by name
     */
    public static function countries(): array
    {
        return self::$countries ??= self::load('ICUDATA-region', 'Countries', '/^[A-Z]{2}$/', self::NOT_COUNTRIES);
    }

    /**
     * @return array<string, string> code → English name, sorted by name
     */
    public static function languages(): array
    {
        return self::$languages ??= self::load('ICUDATA-lang', 'Languages', '/^[a-z]{2}$/', []);
    }

    /**
     * @param  list<string>  $exclude
     * @return array<string, string>
     */
    private static function load(string $bundle, string $table, string $pattern, array $exclude): array
    {
        $names = [];

        foreach (ResourceBundle::create('en', $bundle)?->get($table) ?? [] as $code => $name) {
            if (is_string($code) && is_string($name) && preg_match($pattern, $code) === 1 && ! in_array($code, $exclude, true)) {
                $names[$code] = $name;
            }
        }

        asort($names, SORT_NATURAL | SORT_FLAG_CASE);

        return $names;
    }
}
