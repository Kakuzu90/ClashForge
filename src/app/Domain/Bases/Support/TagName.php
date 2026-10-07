<?php

namespace App\Domain\Bases\Support;

use App\Support\ValueObjects\StringValueObject;

/**
 * A base tag (FR-BASE-4): lowercase-kebab, at most `bases.tag_max_length` characters. "Anti Air"
 * and "anti_air" both become `anti-air`.
 */
final class TagName extends StringValueObject
{
    protected static function normalize(string $value): string
    {
        $value = strtolower(trim($value));
        $value = (string) preg_replace('/[\s_]+/', '-', $value);
        $value = (string) preg_replace('/[^a-z0-9-]/', '', $value);

        return trim((string) preg_replace('/-{2,}/', '-', $value), '-');
    }

    protected static function validate(string $value): ?string
    {
        $max = (int) config('bases.tag_max_length');

        if ($value === '') {
            return 'A tag needs at least one letter or number.';
        }

        return strlen($value) > $max ? "A tag has at most {$max} characters." : null;
    }
}
