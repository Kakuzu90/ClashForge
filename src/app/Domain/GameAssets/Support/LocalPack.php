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
    /** @var array<string, array{path: string, sha256: string, bytes: int, width: int|null, height: int|null, mime: string}>|null */
    private ?array $files = null;

    public function __construct(public readonly string $root) {}

    /**
     * Read and hashed once per instance. Publish re-checks every stored object's checksum after
     * upload, so a file changed after this read is still caught.
     *
     * @return array<string, array{path: string, sha256: string, bytes: int, width: int|null, height: int|null, mime: string}>
     *                                                                                                                         keyed by pack-relative key
     */
    public function files(): array
    {
        if ($this->files !== null) {
            return $this->files;
        }

        $files = [];
        $finfo = new finfo(FILEINFO_MIME_TYPE);

        /** @var SplFileInfo $file */
        foreach (File::allFiles($this->root) as $file) {
            $key = str_replace('\\', '/', $file->getRelativePathname());

            // `name:Zone.Identifier` is the download marker Windows leaves next to a file copied into WSL.
            if ($key === PackManifest::FILENAME || str_starts_with(basename($key), '.') || str_ends_with($key, ':Zone.Identifier')) {
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

        return $this->files = $files;
    }

    public function manifestPath(): string
    {
        return rtrim($this->root, '/').'/'.PackManifest::FILENAME;
    }

    /**
     * Files that may not be packed as they are: the wrong type, an extension that disagrees with
     * the real signature, or over the size limit. Files are never converted or resized
     * (specs/10 §11.2 step 1), so the fix is a different source file.
     *
     * @return list<string>
     */
    public function fileProblems(): array
    {
        $problems = [];

        foreach ($this->files() as $key => $file) {
            array_push($problems, ...self::problemsWith($key, $file));
        }

        return $problems;
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

        foreach ($manifest->entries() as $key => $entry) {
            $file = $files[$key] ?? null;

            if ($file === null) {
                $problems[] = "{$key}: listed in the manifest but not in the folder";

                continue;
            }

            array_push($problems, ...self::problemsWith($key, $file));

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

    /**
     * @param  array{mime: string, bytes: int}  $file
     * @return list<string>
     */
    private static function problemsWith(string $key, array $file): array
    {
        /** @var array<string, string> $mimes */
        $mimes = config('assets.mimes');
        $maxBytes = (int) config('assets.max_bytes');
        $extension = strtolower(pathinfo($key, PATHINFO_EXTENSION));
        $problems = [];

        if (! array_key_exists($file['mime'], $mimes)) {
            $problems[] = "{$key}: {$file['mime']} is not an allowed asset type";
        } elseif ($mimes[$file['mime']] !== $extension) {
            $problems[] = "{$key}: the file is {$file['mime']}, so it must be named .{$mimes[$file['mime']]}";
        }

        if ($file['bytes'] > $maxBytes) {
            $problems[] = "{$key}: {$file['bytes']} bytes is over the {$maxBytes}-byte limit; find a smaller original";
        }

        return $problems;
    }
}
