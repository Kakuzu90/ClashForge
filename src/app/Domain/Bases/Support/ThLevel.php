<?php

namespace App\Domain\Bases\Support;

use InvalidArgumentException;

/**
 * The Town Hall a base is for (FR-BASE-1): `bases.th_min` to `bases.th_max`. The maximum is config,
 * raised when the game adds a Town Hall. We cannot check it against the link (specs/23 §3).
 */
final readonly class ThLevel
{
    public function __construct(public int $value)
    {
        if (($error = self::errorFor($value)) !== null) {
            throw new InvalidArgumentException($error);
        }
    }

    public static function errorFor(int $value): ?string
    {
        $min = (int) config('bases.th_min');
        $max = (int) config('bases.th_max');

        return $value < $min || $value > $max ? "Choose a Town Hall from {$min} to {$max}." : null;
    }
}
