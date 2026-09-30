<?php

namespace App\Domain\GameAssets\Support;

use App\Domain\GameAssets\Enums\GameAssetCategory;
use App\Domain\GameAssets\Enums\Village;

/**
 * One asset in a pack. `ref` is how the API names it: the unit name, the Town Hall level or the
 * league id.
 */
final readonly class ManifestEntry
{
    public function __construct(
        public string $key,
        public GameAssetCategory $category,
        public string $ref,
        public string $displayName,
        public ?Village $village,
        public string $source,
        public string $sha256,
        public int $bytes,
        public int $width,
        public int $height,
    ) {}

    public function lookupKey(): string
    {
        return self::lookup($this->category->isUnit() ? 'unit' : $this->category->value, $this->ref, $this->village);
    }

    /**
     * Units are matched case-insensitively by API name within a village.
     */
    public static function lookup(string $kind, string $ref, ?Village $village = null): string
    {
        return $kind.':'.($village->value ?? '-').':'.mb_strtolower(trim($ref));
    }

    /**
     * @return array<string, int|string|null>
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'category' => $this->category->value,
            'ref' => $this->ref,
            'display_name' => $this->displayName,
            'village' => $this->village?->value,
            'source' => $this->source,
            'sha256' => $this->sha256,
            'bytes' => $this->bytes,
            'width' => $this->width,
            'height' => $this->height,
        ];
    }
}
