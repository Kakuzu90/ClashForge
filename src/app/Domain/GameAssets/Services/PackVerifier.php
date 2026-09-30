<?php

namespace App\Domain\GameAssets\Services;

use App\Domain\GameAssets\Support\PackManifest;
use App\Domain\GameAssets\Support\VerifyReport;
use Illuminate\Support\Facades\Storage;

/**
 * `assets:verify-pack` (specs/10 §9): the bucket copy of a pack still matches its committed
 * manifest, object by object, so "unmodified" stays demonstrable.
 */
class PackVerifier
{
    public function __construct(private readonly GameAssetPolicy $policy) {}

    public function verify(string $version): VerifyReport
    {
        $manifest = PackManifest::fromFile($this->policy->manifestPath($version));
        $disk = Storage::disk((string) config('assets.disk'));
        $prefix = $this->policy->prefix($version);

        $stored = array_map(fn (string $path): string => substr($path, strlen($prefix)), $disk->files($prefix, true));
        $expected = [...array_keys($manifest->entries()), PackManifest::FILENAME];

        $altered = [];

        foreach ($manifest->entries() as $key => $entry) {
            if (in_array($key, $stored, true) && hash('sha256', (string) $disk->get($prefix.$key)) !== $entry->sha256) {
                $altered[] = $key;
            }
        }

        // The bucket manifest must say what the committed one says.
        // Byte for byte: the publisher writes exactly toJson(), so any other bytes are a swap.
        if (in_array(PackManifest::FILENAME, $stored, true) && (string) $disk->get($prefix.PackManifest::FILENAME) !== $manifest->toJson()) {
            $altered[] = PackManifest::FILENAME;
        }

        return new VerifyReport(
            version: $version,
            missing: array_values(array_diff($expected, $stored)),
            extra: array_values(array_diff($stored, $expected)),
            altered: $altered,
        );
    }
}
