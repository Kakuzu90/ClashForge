<?php

namespace Tests\Support\Media;

use App\Domain\Media\Enums\MediaCollection;
use App\Domain\Media\Models\Media;
use App\Models\User;
use DateTimeInterface;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;

/**
 * A faked media disk that can presign (the local fake cannot) and helpers to stage an upload the
 * way the browser leaves it: a row in `uploaded` and bytes under its quarantine key.
 */
trait InteractsWithMedia
{
    protected function fakeMediaStorage(): FilesystemAdapter
    {
        config([
            'media.presign_host' => null,
            'media.cdn_url' => 'https://cdn.test',
            'media.processing.min_free_bytes' => 0,
            'media.processing.temp_dir' => sys_get_temp_dir().'/clashcommons-media-tests/'.getmypid(),
        ]);

        /** @var FilesystemAdapter $disk */
        $disk = Storage::fake((string) config('media.disk'));

        $disk->buildTemporaryUploadUrlsUsing(fn (string $path, DateTimeInterface $expiration): array => [
            'url' => 'https://storage.test/'.$path.'?expires='.$expiration->getTimestamp().'&signature=test',
            'headers' => [],
        ]);
        $disk->buildTemporaryUrlsUsing(fn (string $path, DateTimeInterface $expiration): string => 'https://storage.test/'.$path.'?expires='.$expiration->getTimestamp().'&signature=test');

        return $disk;
    }

    protected function mediaDisk(): FilesystemAdapter
    {
        /** @var FilesystemAdapter */
        return Storage::disk((string) config('media.disk'));
    }

    protected function uploadedMedia(
        string $bytes,
        MediaCollection $collection = MediaCollection::BaseScreenshot,
        ?User $owner = null,
        ?int $declaredSize = null,
    ): Media {
        $media = Media::factory()
            ->collection($collection)
            ->uploaded()
            ->create([
                'user_id' => ($owner ?? User::factory()->create())->id,
                'size_bytes' => $declaredSize ?? strlen($bytes),
            ]);

        $this->mediaDisk()->put($media->path, $bytes);

        return $media;
    }
}
