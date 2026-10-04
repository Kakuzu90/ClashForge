<?php

namespace App\Domain\GameAssets\Services;

use App\Domain\GameAssets\Enums\GameAssetCategory;
use App\Domain\GameAssets\Enums\Village;
use App\Domain\GameAssets\Exceptions\InvalidManifest;
use App\Domain\GameAssets\Support\LocalPack;
use App\Domain\GameAssets\Support\PackManifest;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Writes or refreshes a pack folder's manifest.json (specs/10 §11.2 step 2). Checksums, sizes and
 * dimensions always come from the files, category and village from the folder; the fields staff
 * fill in (API name, display name, source) are kept across runs.
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
            $category = GameAssetCategory::fromFolder(explode('/', $key)[0]);
            $village = PackManifest::villageFor($category, $key);

            $assets[] = [
                'key' => $key,
                'category' => $category->value ?? ($previous['category'] ?? null),
                'ref' => $previous['ref'] ?? $this->ref($category, $village, $slug),
                'display_name' => $previous['display_name'] ?? $this->displayName($category, $village, $slug),
                'village' => $village?->value,
                'source' => $previous['source'] ?? '',
                'sha256' => $file['sha256'],
                'bytes' => $file['bytes'],
                'width' => $file['width'],
                'height' => $file['height'],
            ];
        }

        $json = json_encode(
            ['version' => $version, 'assets' => $assets],
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
        )."\n";

        try {
            // Canonical bytes, so publish-pack can compare this file with the committed copy.
            $json = PackManifest::fromJson($json)->toJson();
            $problems = [];
        } catch (InvalidManifest $e) {
            $problems = $e->problems;
        }

        file_put_contents($pack->manifestPath(), $json);
        $problems = [...$pack->fileProblems(), ...$problems];

        Log::info('assets.manifest_built', ['path' => $root, 'version' => $version, 'assets' => count($assets), 'problems' => count($problems)]);

        return $problems;
    }

    /**
     * A new unit file's ref is its guessed API name ("healing" → "Healing Spell"); names the rule
     * gets wrong (P.E.K.K.A, L.A.S.S.I) are fixed by hand and kept across runs.
     */
    private function ref(?GameAssetCategory $category, ?Village $village, string $slug): string
    {
        return $category?->isUnit() === true ? $this->displayName($category, $village, $slug) : $slug;
    }

    private function displayName(?GameAssetCategory $category, ?Village $village, string $slug): string
    {
        if ($category === GameAssetCategory::Spell) {
            return Str::headline($slug).' Spell';
        }

        if ($category !== GameAssetCategory::TownHall) {
            return Str::headline($slug);
        }

        return $village === Village::Builder ? "Builder Hall {$slug}" : "Town Hall {$slug}";
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
