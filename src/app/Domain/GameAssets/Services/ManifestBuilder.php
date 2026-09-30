<?php

namespace App\Domain\GameAssets\Services;

use App\Domain\GameAssets\Enums\GameAssetCategory;
use App\Domain\GameAssets\Exceptions\InvalidManifest;
use App\Domain\GameAssets\Support\LocalPack;
use App\Domain\GameAssets\Support\PackManifest;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Writes or refreshes a pack folder's manifest.json (specs/10 §11.2 step 2). Checksums, sizes and
 * dimensions always come from the files; the fields staff fill in (API name, display name,
 * category, village, source) are kept across runs.
 */
class ManifestBuilder
{
    /**
     * @return list<string> what still needs a human before the pack can be published
     */
    public function build(string $root, string $version): array
    {
        $pack = new LocalPack($root);
        $existing = $this->existingEntries($pack->manifestPath());
        $assets = [];

        foreach ($pack->files() as $key => $file) {
            $previous = $existing[$key] ?? [];
            $slug = pathinfo($key, PATHINFO_FILENAME);
            $category = $this->categoryFor($key);

            $assets[] = [
                'key' => $key,
                'category' => $previous['category'] ?? $category?->value,
                'ref' => $previous['ref'] ?? ($category?->isUnit() === false ? $slug : Str::headline($slug)),
                'display_name' => $previous['display_name'] ?? $this->displayName($category, $slug),
                'village' => $previous['village'] ?? null,
                'source' => $previous['source'] ?? '',
                'sha256' => $file['sha256'],
                'bytes' => $file['bytes'],
                'width' => $file['width'],
                'height' => $file['height'],
            ];
        }

        file_put_contents($pack->manifestPath(), json_encode(
            ['version' => $version, 'assets' => $assets],
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
        )."\n");

        try {
            PackManifest::fromFile($pack->manifestPath());
            $problems = [];
        } catch (InvalidManifest $e) {
            $problems = $e->problems;
        }

        Log::info('assets.manifest_built', ['path' => $root, 'version' => $version, 'assets' => count($assets), 'problems' => count($problems)]);

        return $problems;
    }

    /**
     * Only town halls and leagues can be told from the folder; units need a category by hand.
     */
    private function categoryFor(string $key): ?GameAssetCategory
    {
        return match (explode('/', $key)[0]) {
            'townhalls' => GameAssetCategory::TownHall,
            'leagues' => GameAssetCategory::League,
            default => null,
        };
    }

    private function displayName(?GameAssetCategory $category, string $slug): string
    {
        return $category === GameAssetCategory::TownHall ? "Town Hall {$slug}" : Str::headline($slug);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function existingEntries(string $path): array
    {
        if (! is_file($path)) {
            return [];
        }

        $data = json_decode((string) file_get_contents($path), true);
        $entries = [];

        foreach ((is_array($data) && is_array($data['assets'] ?? null)) ? $data['assets'] : [] as $asset) {
            if (is_array($asset) && is_string($asset['key'] ?? null)) {
                $entries[$asset['key']] = array_filter($asset, fn (mixed $value): bool => $value !== null && $value !== '');
            }
        }

        return $entries;
    }
}
