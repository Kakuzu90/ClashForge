<?php

namespace App\Domain\CocIntegration\Support;

use App\Domain\CocIntegration\Exceptions\CocApiFailure;

/**
 * Typed reads over a decoded API object. A missing field is null (or an empty list), because the
 * API drops fields without notice; a field that is present with the wrong type means the shape
 * changed, and the whole response is refused (specs/23 §5: nothing is partly written).
 */
final class Payload
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function __construct(
        private readonly array $data,
        private readonly string $path,
    ) {}

    /**
     * @throws CocApiFailure when the body is not a JSON object
     */
    public static function decode(string $body, string $path): self
    {
        $data = json_decode($body, true);

        if (! is_array($data) || ($data !== [] && array_is_list($data))) {
            throw CocApiFailure::malformed("{$path}: the body is not a JSON object");
        }

        /** @var array<string, mixed> $data */
        return new self($data, $path);
    }

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return $this->data;
    }

    public function string(string $key): string
    {
        $value = $this->nullableString($key);

        if ($value === null || $value === '') {
            throw $this->problem($key, 'is missing');
        }

        return $value;
    }

    public function nullableString(string $key): ?string
    {
        $value = $this->data[$key] ?? null;

        if ($value !== null && ! is_string($value)) {
            throw $this->problem($key, 'is not a string');
        }

        return $value;
    }

    public function int(string $key): int
    {
        return $this->nullableInt($key) ?? throw $this->problem($key, 'is missing');
    }

    public function nullableInt(string $key): ?int
    {
        $value = $this->data[$key] ?? null;

        if ($value !== null && ! is_int($value)) {
            throw $this->problem($key, 'is not an integer');
        }

        return $value;
    }

    public function bool(string $key): bool
    {
        $value = $this->data[$key] ?? false;

        if (! is_bool($value)) {
            throw $this->problem($key, 'is not a boolean');
        }

        return $value;
    }

    public function object(string $key): ?self
    {
        $value = $this->data[$key] ?? null;

        if ($value === null) {
            return null;
        }

        if (! is_array($value) || ($value !== [] && array_is_list($value))) {
            throw $this->problem($key, 'is not an object');
        }

        /** @var array<string, mixed> $value */
        return new self($value, "{$this->path}.{$key}");
    }

    /**
     * A list of objects; missing reads as empty.
     *
     * @return list<self>
     */
    public function objects(string $key): array
    {
        $value = $this->data[$key] ?? [];

        if (! is_array($value) || ! array_is_list($value)) {
            throw $this->problem($key, 'is not a list');
        }

        $items = [];

        foreach ($value as $i => $item) {
            if (! is_array($item) || ($item !== [] && array_is_list($item))) {
                throw $this->problem("{$key}[{$i}]", 'is not an object');
            }

            /** @var array<string, mixed> $item */
            $items[] = new self($item, "{$this->path}.{$key}[{$i}]");
        }

        return $items;
    }

    /**
     * A size → URL map (`badgeUrls`, `iconUrls`), kept verbatim; missing reads as empty.
     *
     * @return array<string, string>
     */
    public function urls(string $key): array
    {
        $value = $this->data[$key] ?? [];

        if (! is_array($value)) {
            throw $this->problem($key, 'is not an object');
        }

        $urls = [];

        foreach ($value as $size => $url) {
            if (! is_string($size) || ! is_string($url)) {
                throw $this->problem($key, 'holds a value that is not a URL string');
            }

            $urls[$size] = $url;
        }

        return $urls;
    }

    private function problem(string $key, string $what): CocApiFailure
    {
        return CocApiFailure::malformed("{$this->path}.{$key} {$what}");
    }
}
