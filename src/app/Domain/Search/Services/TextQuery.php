<?php

namespace App\Domain\Search\Services;

use Illuminate\Support\Facades\DB;

/**
 * The full-text query every Postgres source matches with (specs/17 §3). `websearch_to_tsquery`
 * accepts any input, quoted phrases and `-exclusions` included, without a syntax error. Names and
 * tags are indexed with the `simple` configuration and prose with `english`, so the text is
 * matched both ways. Always bound, never interpolated (specs/11 §2).
 */
final class TextQuery
{
    public const NAMES = "websearch_to_tsquery('simple', ?)";

    public const ANY = "(websearch_to_tsquery('simple', ?) || websearch_to_tsquery('english', ?))";

    /**
     * @return list<string>
     */
    public static function bindings(string $term): array
    {
        return [$term, $term];
    }

    /**
     * Whether the text has something to look up in an index. A query made only of exclusions
     * (`-zz`), or one that ORs an exclusion in, matches every row that lacks the word and would
     * scan the whole table; punctuation alone matches nothing. Postgres's `querytree` says which:
     * `T` for "not indexable", empty for "nothing to match" (specs/17 §4, P3-05).
     */
    public static function indexable(string $term): bool
    {
        if (trim($term) === '' || DB::getDriverName() !== 'pgsql') {
            return trim($term) !== '';
        }

        $tree = DB::selectOne('SELECT querytree('.self::ANY.') AS tree', self::bindings($term))->tree ?? '';

        return ! in_array(trim((string) $tree), ['', 'T'], true);
    }
}
