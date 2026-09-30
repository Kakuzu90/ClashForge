<?php

namespace App\Domain\Users\Queries;

use App\Domain\Users\Support\IsoCodes;
use DateTimeZone;

/**
 * The lists the profile form picks from, matching what the value objects accept.
 */
class ProfileChoicesQuery
{
    /**
     * @return list<array{value: string, label: string}>
     */
    public function countries(): array
    {
        return self::options(IsoCodes::countries());
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public function languages(): array
    {
        return self::options(IsoCodes::languages());
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public function timezones(): array
    {
        $zones = [];

        foreach (DateTimeZone::listIdentifiers() as $zone) {
            $zones[$zone] = str_replace('_', ' ', $zone);
        }

        return self::options($zones);
    }

    /**
     * @param  array<string, string>  $names
     * @return list<array{value: string, label: string}>
     */
    private static function options(array $names): array
    {
        $options = [];

        foreach ($names as $value => $label) {
            $options[] = ['value' => (string) $value, 'label' => $label];
        }

        return $options;
    }
}
