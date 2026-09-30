<?php

namespace App\Support\Casts;

use App\Support\ValueObjects\StringValueObject;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * Casts a string column to a StringValueObject subclass: `'tag' => AsValueObject::class.':'.PlayerTag::class`.
 *
 * @implements CastsAttributes<StringValueObject, StringValueObject|string>
 */
class AsValueObject implements CastsAttributes
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

    public function get(Model $model, string $key, mixed $value, array $attributes): ?StringValueObject
    {
        return $value === null ? null : $this->class::from((string) $value);
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof StringValueObject) {
            if (! $value instanceof $this->class) {
                throw new InvalidArgumentException("{$key} expects ".$this->class.', got '.$value::class);
            }

            return $value->value;
        }

        return $this->class::from((string) $value)->value;
    }
}
