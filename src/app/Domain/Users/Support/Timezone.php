<?php

namespace App\Domain\Users\Support;

use App\Support\ValueObjects\StringValueObject;
use DateTimeZone;

/**
 * A PHP/IANA timezone identifier such as `Europe/Berlin`. Display only: times stay UTC (specs/23 §9).
 */
final class Timezone extends StringValueObject
{
    protected static function validate(string $value): ?string
    {
        return in_array($value, DateTimeZone::listIdentifiers(), true) ? null : 'Choose a timezone from the list.';
    }
}
