<?php

namespace Tests\Support\Fixtures;

use App\Support\ValueObjects\StringValueObject;

/**
 * Test-only value object: 3–8 uppercase letters or digits, input is trimmed and uppercased.
 */
final class SampleCode extends StringValueObject
{
    protected static function normalize(string $value): string
    {
        return strtoupper(trim($value));
    }

    protected static function validate(string $value): ?string
    {
        return preg_match('/^[A-Z0-9]{3,8}$/', $value) === 1 ? null : 'Must be 3 to 8 letters or digits.';
    }
}
