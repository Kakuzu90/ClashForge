<?php

namespace App\Domain\GameAssets\Support;

use App\Domain\GameAssets\Enums\GameAssetCategory;
use App\Domain\GameAssets\Enums\Village;
use App\Domain\GameAssets\Exceptions\InvalidManifest;
use JsonException;

/**
 * A pack's manifest.json (specs/10 §11.2): provenance, checksum and dimensions per asset. Parsing
 * validates everything, so an instance is always usable.
 */
final class PackManifest
{
    public const FILENAME = 'manifest.json';

    // Relative, lower-case, no traversal: the key becomes part of a public URL and a bucket key.
    private const KEY_PATTERN = '#^(units|townhalls|leagues)/[a-z0-9][a-z0-9_-]*\.[a-z0-9]+$#D';

    /** @var array<string, ManifestEntry> keyed by lookup key */
    private array $index = [];

    /**
     * @param  array<string, ManifestEntry>  $entries  keyed by object key
     */
    private function __construct(public readonly string $version, private readonly array $entries)
    {
        foreach ($entries as $entry) {
            $this->index[$entry->lookupKey()] = $entry;
        }
    }

    public static function fromFile(string $path): self
    {
        if (! is_file($path)) {
            throw new InvalidManifest(["{$path} does not exist"]);
        }

        return self::fromJson((string) file_get_contents($path));
    }

    public static function fromJson(string $json): self
    {
        try {
            $data = json_decode($json, true, 16, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new InvalidManifest(['not valid JSON: '.$e->getMessage()]);
        }

        if (! is_array($data) || ! is_scalar($data['version'] ?? null) || (string) $data['version'] === '' || ! is_array($data['assets'] ?? null)) {
            throw new InvalidManifest(['expected {"version": "...", "assets": [...]}']);
        }

        $problems = [];
        $entries = [];
        $lookups = [];

        foreach (array_values($data['assets']) as $i => $raw) {
            $entry = is_array($raw) ? self::entry($raw, $problems, "assets[{$i}]") : null;

            if ($entry === null) {
                if (! is_array($raw)) {
                    $problems[] = "assets[{$i}]: not an object";
                }

                continue;
            }

            if (isset($entries[$entry->key])) {
                $problems[] = "{$entry->key}: listed twice";
            }

            if (isset($lookups[$entry->lookupKey()])) {
                $problems[] = "{$entry->key}: same {$entry->category->value} and name as {$lookups[$entry->lookupKey()]}";
            }

            $entries[$entry->key] = $entry;
            $lookups[$entry->lookupKey()] = $entry->key;
        }

        if ($problems !== []) {
            throw new InvalidManifest($problems);
        }

        ksort($entries);

        return new self((string) $data['version'], $entries);
    }

    /**
     * @param  list<ManifestEntry>  $entries
     */
    public static function make(string $version, array $entries): self
    {
        return self::fromJson((string) json_encode([
            'version' => $version,
            'assets' => array_map(fn (ManifestEntry $e): array => $e->toArray(), $entries),
        ]));
    }

    public function find(string $kind, string $ref, ?Village $village = null): ?ManifestEntry
    {
        return $this->index[ManifestEntry::lookup($kind, $ref, $village)] ?? null;
    }

    public function get(string $key): ?ManifestEntry
    {
        return $this->entries[$key] ?? null;
    }

    /**
     * @return array<string, ManifestEntry>
     */
    public function entries(): array
    {
        return $this->entries;
    }

    public function toJson(): string
    {
        return json_encode([
            'version' => $this->version,
            'assets' => array_values(array_map(fn (ManifestEntry $e): array => $e->toArray(), $this->entries)),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)."\n";
    }

    /**
     * @param  array<mixed>  $raw
     * @param  list<string>  $problems
     */
    private static function entry(array $raw, array &$problems, string $where): ?ManifestEntry
    {
        $before = count($problems);
        $key = is_string($raw['key'] ?? null) ? $raw['key'] : '';
        $where = $key !== '' ? $key : $where;

        if (! preg_match(self::KEY_PATTERN, $key)) {
            $problems[] = "{$where}: key must look like units/barbarian.png (lower case, no ..)";
        }

        $category = GameAssetCategory::tryFrom(is_string($raw['category'] ?? null) ? $raw['category'] : '');
        $village = isset($raw['village']) && is_string($raw['village']) ? Village::tryFrom($raw['village']) : null;

        if ($category === null) {
            $problems[] = "{$where}: category must be one of ".implode(', ', GameAssetCategory::values());
        } elseif ($key !== '' && ! str_starts_with($key, $category->folder().'/')) {
            $problems[] = "{$where}: a {$category->value} belongs in {$category->folder()}/";
        }

        if ($category?->isUnit() && $village === null) {
            $problems[] = "{$where}: units need a village (".implode(' or ', Village::values()).')';
        }

        foreach (['ref', 'display_name', 'source'] as $field) {
            if (! is_string($raw[$field] ?? null) || trim($raw[$field]) === '') {
                $problems[] = "{$where}: {$field} is required";
            }
        }

        if (! is_string($raw['sha256'] ?? null) || ! preg_match('/^[0-9a-f]{64}$/D', $raw['sha256'])) {
            $problems[] = "{$where}: sha256 must be 64 lower-case hex characters";
        }

        foreach (['bytes', 'width', 'height'] as $field) {
            if (! is_int($raw[$field] ?? null) || $raw[$field] < 1) {
                $problems[] = "{$where}: {$field} must be a positive integer";
            }
        }

        if (count($problems) > $before || $category === null) {
            return null;
        }

        return new ManifestEntry(
            key: $key,
            category: $category,
            ref: (string) $raw['ref'],
            displayName: (string) $raw['display_name'],
            village: $category->isUnit() ? $village : null,
            source: (string) $raw['source'],
            sha256: (string) $raw['sha256'],
            bytes: (int) $raw['bytes'],
            width: (int) $raw['width'],
            height: (int) $raw['height'],
        );
    }
}
