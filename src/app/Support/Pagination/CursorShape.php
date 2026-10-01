<?php

namespace App\Support\Pagination;

/**
 * Whether a cursor from a query string has the shape Laravel's cursor paginator issues for a
 * single integer column: base64url JSON with the direction flag and that column. Anything else
 * would make the paginator throw (a 500) instead of returning a page, so Form Requests reject it.
 */
final class CursorShape
{
    public static function accepts(string $cursor, string $column): bool
    {
        return is_int(self::parameters($cursor)[$column] ?? null);
    }

    /**
     * The decoded cursor when it has the paginator's direction flag, for checking other column
     * shapes; null otherwise.
     *
     * @return array<string, mixed>|null
     */
    public static function parameters(string $cursor): ?array
    {
        $json = base64_decode(str_replace(['-', '_'], ['+', '/'], $cursor), true);
        $parameters = $json === false ? null : json_decode($json, true);

        return is_array($parameters) && is_bool($parameters['_pointsToNextItems'] ?? null) ? $parameters : null;
    }
}
