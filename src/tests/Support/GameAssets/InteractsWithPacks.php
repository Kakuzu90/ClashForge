<?php

namespace Tests\Support\GameAssets;

use App\Domain\GameAssets\Services\ManifestBuilder;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

/**
 * A small pack folder on disk (original test images, not game art), a complete manifest for it,
 * a faked bucket and a temp repo path for committed manifests.
 */
trait InteractsWithPacks
{
    protected string $packDir;

    protected function setUpPack(): void
    {
        $base = sys_get_temp_dir().'/clashcommons-packs/'.getmypid().'-'.uniqid();
        $this->packDir = "{$base}/source";

        config([
            'assets.enabled' => true,
            'assets.pack_version' => null,
            'assets.cdn_url' => 'https://cdn.test',
            'assets.manifest_path' => "{$base}/repo/{version}/manifest.json",
        ]);

        Storage::fake((string) config('assets.disk'));

        foreach (['units/barbarian.png', 'units/archer-queen.png', 'townhalls/16.png', 'leagues/29000022.png'] as $i => $key) {
            File::ensureDirectoryExists(dirname("{$this->packDir}/{$key}"));
            file_put_contents("{$this->packDir}/{$key}", self::png(64 + $i));
        }

        app(ManifestBuilder::class)->build($this->packDir, '1');
        $this->editManifest(function (array $asset): array {
            return match ($asset['key']) {
                'units/barbarian.png' => [...$asset, 'category' => 'troop', 'village' => 'home', 'source' => 'test fixture'],
                'units/archer-queen.png' => [...$asset, 'category' => 'hero', 'village' => 'home', 'source' => 'test fixture'],
                'leagues/29000022.png' => [...$asset, 'display_name' => 'Legend League', 'source' => 'test fixture'],
                default => [...$asset, 'source' => 'test fixture'],
            };
        });
    }

    protected function tearDownPack(): void
    {
        File::deleteDirectory(dirname($this->packDir));
    }

    /**
     * @param  callable(array<string, mixed>): array<string, mixed>  $edit
     */
    protected function editManifest(callable $edit, ?string $dir = null): void
    {
        $path = ($dir ?? $this->packDir).'/manifest.json';
        $data = json_decode((string) file_get_contents($path), true);
        $data['assets'] = array_map($edit, $data['assets']);
        file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    protected function assetsDisk(): FilesystemAdapter
    {
        /** @var FilesystemAdapter */
        return Storage::disk((string) config('assets.disk'));
    }

    protected static function png(int $size): string
    {
        $image = imagecreatetruecolor($size, $size);
        imagefilledellipse($image, intdiv($size, 2), intdiv($size, 2), $size - 4, $size - 4, (int) imagecolorallocate($image, 240, 190, 40));
        ob_start();
        imagepng($image);

        return (string) ob_get_clean();
    }
}
