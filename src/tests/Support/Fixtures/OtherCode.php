<?php

namespace Tests\Support\Fixtures;

use App\Support\ValueObjects\StringValueObject;

final class OtherCode extends StringValueObject
{
    protected static function validate(string $value): ?string
    {
        return $value === '' ? 'Required.' : null;
    }
}
