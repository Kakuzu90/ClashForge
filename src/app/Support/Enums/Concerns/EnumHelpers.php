<?php

namespace App\Support\Enums\Concerns;

use App\Support\Enums\Contracts\HasLabelAndColor;

/**
 * Helpers for string-backed enums that implement HasLabelAndColor.
 *
 * @phpstan-require-implements HasLabelAndColor
 */
trait EnumHelpers
{
    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Select/filter options, in declaration order.
     *
     * @return list<array{value: string, label: string, color: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $case): array => ['value' => $case->value, 'label' => $case->label(), 'color' => $case->color()],
            self::cases(),
        );
    }
}
