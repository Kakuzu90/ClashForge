<?php

namespace App\Support\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Valid UTF-8 without NUL bytes. Laravel's `string` rule lets other bytes through, and Postgres
 * then rejects the bound value (SQLSTATE 22021), which would be a 500 instead of a field error.
 */
class Utf8Text implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (is_string($value) && (! mb_check_encoding($value, 'UTF-8') || str_contains($value, "\0"))) {
            $fail('The :attribute contains characters that are not allowed.');
        }
    }
}
