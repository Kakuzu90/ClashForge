<?php

namespace App\Domain\GameAssets\Services;

use App\Domain\GameAssets\Exceptions\InvalidManifest;
use App\Domain\GameAssets\Exceptions\PackPublishFailed;
use App\Domain\GameAssets\Support\LocalPack;
use App\Domain\GameAssets\Support\PackManifest;
use Closure;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * `assets:publish-pack` (specs/10 §11.2 step 3–4): uploads a pack byte-for-byte to an unused
 * `game/{version}/` prefix, re-reads every object to prove it is unmodified, and removes everything
 * it wrote if any step fails, so a partial pack never exists. Activation stays a config change.
 */
class PackPublisher
{
    public function __construct(private readonly GameAssetPolicy $policy) {}

    /**
     * @param  (Closure(string): void)|null  $progress  called with each key as it is verified
     *
     * @throws InvalidManifest when the folder and its manifest disagree
     * @throws PackPublishFailed when the version exists or an upload does not verify
     */
    public function publish(string $root, string $version, ?Closure $progress = null): PackManifest
    {
        $pack = new LocalPack($root);
        $manifest = PackManifest::fromFile($pack->manifestPath());

        if ($manifest->version !== $version) {
            throw new InvalidManifest(["manifest is for version {$manifest->version}, not {$version}"]);
        }

        if (($problems = $pack->problemsAgainst($manifest)) !== []) {
            throw new InvalidManifest($problems);
        }

        $repoCopy = $this->policy->manifestPath($version);

        // A version name stands for one pack everywhere: never rewrite a committed manifest.
        if (is_file($repoCopy) && (string) file_get_contents($repoCopy) !== $manifest->toJson()) {
            throw new PackPublishFailed("{$repoCopy} already describes a different pack; publish under a new version. Nothing was uploaded.");
        }

        // The bucket check below and the writes after it must not interleave with another run.
        $lock = Cache::lock("assets:publish:{$version}", (int) config('assets.publish_lock_seconds'));

        if (! $lock->get()) {
            throw new PackPublishFailed("Another publish of version {$version} is running. Nothing was uploaded.");
        }

        try {
            return $this->upload($manifest, $pack, $version, $repoCopy, $progress);
        } finally {
            $lock->release();
        }
    }

    /**
     * @param  (Closure(string): void)|null  $progress
     */
    private function upload(PackManifest $manifest, LocalPack $pack, string $version, string $repoCopy, ?Closure $progress): PackManifest
    {
        $disk = Storage::disk((string) config('assets.disk'));
        $prefix = $this->policy->prefix($version);

        // Versions are immutable: a corrected asset means a new version (specs/10 §11.3).
        if ($disk->files($prefix, true) !== []) {
            throw new PackPublishFailed("{$prefix} already has objects; publish under a new version. Nothing was uploaded.");
        }

        $written = [];
        $files = $pack->files();

        try {
            foreach ($manifest->entries() as $key => $entry) {
                $file = $files[$key];
                // Recorded before the write: a put that fails after storing must still be rolled back.
                $written[] = $prefix.$key;
                $this->put($disk, $prefix.$key, (string) file_get_contents($file['path']), $file['mime']);

                if (hash('sha256', (string) $disk->get($prefix.$key)) !== $entry->sha256) {
                    throw new PackPublishFailed("{$key}: the stored object does not match the manifest checksum.");
                }

                $progress?->__invoke($key);
            }

            // Last, so a bucket manifest only ever describes a complete pack.
            $written[] = $prefix.PackManifest::FILENAME;
            $this->put($disk, $prefix.PackManifest::FILENAME, $manifest->toJson(), 'application/json');
        } catch (Throwable $e) {
            throw new PackPublishFailed($e->getMessage().' '.$this->rollBack($disk, $written), previous: $e);
        }

        File::ensureDirectoryExists(dirname($repoCopy));
        File::put($repoCopy, $manifest->toJson());

        Log::info('assets.pack_published', ['version' => $version, 'assets' => count($manifest->entries())]);

        return $manifest;
    }

    /**
     * @param  list<string>  $written
     */
    private function rollBack(Filesystem $disk, array $written): string
    {
        try {
            $disk->delete($written);

            return 'Everything uploaded in this run was removed.';
        } catch (Throwable $e) {
            Log::error('assets.rollback_failed', ['objects' => $written, 'error' => $e->getMessage()]);

            return 'Removing the uploaded objects also failed; delete them by hand: '.implode(', ', $written);
        }
    }

    private function put(Filesystem $disk, string $path, string $contents, string $mime): void
    {
        $disk->put($path, $contents, [
            'ContentType' => $mime,
            'CacheControl' => (string) config('assets.cache_control'),
        ]);
    }
}
