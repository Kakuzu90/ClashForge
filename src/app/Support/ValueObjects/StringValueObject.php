<?php

namespace App\Support\ValueObjects;

use InvalidArgumentException;
use JsonSerializable;
use Stringable;

/**
 * Immutable wrapper around a validated string. The invariant lives in the constructor, so no code
 * path can hold an invalid instance (specs/05 §3). Subclasses implement normalize() and validate().
 */
abstract class StringValueObject implements JsonSerializable, Stringable
{
    public readonly string $value;

    final public function __construct(string $value)
    {
        $normalized = static::normalize($value);

        if (($error = static::validate($normalized)) !== null) {
            throw new InvalidArgumentException($error);
        }

        $this->value = $normalized;
    }

    public static function from(string $value): static
    {
        return new static($value);
    }

    public static function tryFrom(?string $value): ?static
    {
        if ($value === null) {
            return null;
        }

        try {
            return new static($value);
        } catch (InvalidArgumentException) {
            return null;
        }
    }

    /**
     * The validation message for an invalid input, or null when the input is valid.
     */
    public static function errorFor(string $value): ?string
    {
        return static::validate(static::normalize($value));
    }

    public function equals(self $other): bool
    {
        return $other instanceof static && $other->value === $this->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }

    public function jsonSerialize(): string
    {
        return $this->value;
    }

    protected static function normalize(string $value): string
    {
        return trim($value);
    }

    /**
     * Return an error message when invalid, null when valid.
     */
    abstract protected static function validate(string $value): ?string;
}
