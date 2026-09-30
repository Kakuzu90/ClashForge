<?php

namespace App\Support\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * A list of short strings stored as a native `varchar[]` on Postgres (so GIN indexes and array
 * CHECKs work) and as JSON on SQLite, where tests also run.
 *
 * @implements CastsAttributes<list<string>, list<string>>
 */
class AsStringList implements CastsAttributes
{
    /**
     * @return list<string>
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): array
    {
        if ($value === null || $value === '') {
            return [];
        }

        $raw = (string) $value;

        if (str_starts_with($raw, '{')) {
            $inner = substr($raw, 1, -1);

            return $inner === '' ? [] : array_map(
                fn (string $item): string => stripcslashes(trim($item, '"')),
                str_getcsv($inner, ',', '"', '\\'),
            );
        }

        $decoded = json_decode($raw, true);

        return is_array($decoded) ? array_values(array_map('strval', $decoded)) : [];
    }

    /**
     * @param  list<string>|null  $value
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): string
    {
        $items = array_map('strval', $value ?? []);

        if ($model->getConnection()->getDriverName() !== 'pgsql') {
            return (string) json_encode($items);
        }

        return '{'.implode(',', array_map(fn (string $item): string => '"'.addcslashes($item, '"\\').'"', $items)).'}';
    }
}
