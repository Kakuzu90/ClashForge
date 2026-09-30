<?php

namespace App\Support\Rules;

use App\Support\ValueObjects\StringValueObject;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use InvalidArgumentException;

/**
 * Form Request rule that delegates to the value object, so validation cannot drift from the invariant.
 */
class ValidValueObject implements ValidationRule
{
    /**
     * @param  class-string<StringValueObject>  $class
     */
    public function __construct(private readonly string $class)
    {
        if (! is_subclass_of($class, StringValueObject::class)) {
            throw new InvalidArgumentException("{$class} must extend ".StringValueObject::class);
        }
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            $fail('The :attribute must be a string.');

            return;
        }

        if (($error = $this->class::errorFor($value)) !== null) {
            $fail($error);
        }
    }
}
