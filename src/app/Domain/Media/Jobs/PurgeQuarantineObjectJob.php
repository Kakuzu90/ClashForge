<?php

namespace App\Domain\Media\Jobs;

use App\Domain\Media\Enums\MediaStatus;
use App\Domain\Media\Models\Media;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;

/**
 * Deletes a processed upload's quarantine key once its presigned PUT has expired. The URL stays
 * valid after processing, so without this second pass a client could re-upload any size of
 * object to a key nothing looks at again. Quarantined files are kept for review.
 */
class PurgeQuarantineObjectJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly int $mediaId)
    {
        $this->onQueue((string) config('media.cleanup_queue'));
    }

    public function handle(): void
    {
        $media = Media::query()->find($this->mediaId);

        if ($media === null || ! in_array($media->status, [MediaStatus::Ready, MediaStatus::Failed], true)) {
            return;
        }

        Storage::disk($media->disk)->delete($media->path);
    }
}
