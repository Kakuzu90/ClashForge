<?php

namespace App\Domain\GameAssets\Support;

use finfo;
use Illuminate\Support\Facades\File;
use Symfony\Component\Finder\SplFileInfo;

/**
 * Reads a pack folder on disk as staff assembled it. Nothing here writes to the files: they must
 * stay byte-for-byte what the source supplied (specs/10 §11.2 step 1).
 */
final class LocalPack
{
    public function __construct(public readonly string $root) {}

    /**
     * @return array<string, array{path: string, sha256: string, bytes: int, width: int|null, height: int|null, mime: string}>
     *                                                                                                                         keyed by pack-relative key
     */
    public function files(): array
    {
        $files = [];
        $finfo = new finfo(FILEINFO_MIME_TYPE);

        /** @var SplFileInfo $file */
        foreach (File::allFiles($this->root) as $file) {
            $key = str_replace('\\', '/', $file->getRelativePathname());

            if ($key === PackManifest::FILENAME || str_starts_with(basename($key), '.')) {
                continue;
            }

            $path = $file->getPathname();
            $size = @getimagesize($path);

            $files[$key] = [
                'path' => $path,
                'sha256' => (string) hash_file('sha256', $path),
                'bytes' => (int) filesize($path),
                'width' => $size === false ? null : $size[0],
                'height' => $size === false ? null : $size[1],
                'mime' => (string) $finfo->file($path),
            ];
        }

        ksort($files);

        return $files;
    }

    public function manifestPath(): string
    {
        return rtrim($this->root, '/').'/'.PackManifest::FILENAME;
    }

    /**
     * Every difference between the folder and its manifest; empty when they agree exactly.
     *
     * @return list<string>
     */
    public function problemsAgainst(PackManifest $manifest): array
    {
        $files = $this->files();
        $problems = [];
        /** @var array<string, string> $mimes */
        $mimes = config('assets.mimes');

        foreach ($manifest->entries() as $key => $entry) {
            $file = $files[$key] ?? null;

            if ($file === null) {
                $problems[] = "{$key}: listed in the manifest but not in the folder";

                continue;
            }

            if (! array_key_exists($file['mime'], $mimes)) {
                $problems[] = "{$key}: {$file['mime']} is not an allowed asset type";
            }

            if ($file['sha256'] !== $entry->sha256 || $file['bytes'] !== $entry->bytes) {
                $problems[] = "{$key}: file differs from the manifest checksum or size";
            }

            if ($file['width'] !== $entry->width || $file['height'] !== $entry->height) {
                $problems[] = "{$key}: file is {$file['width']}x{$file['height']}, manifest says {$entry->width}x{$entry->height}";
            }
        }

        foreach (array_diff_key($files, $manifest->entries()) as $key => $_) {
            $problems[] = "{$key}: in the folder but not in the manifest";
        }

        return $problems;
    }
}
