<?php

namespace App\Domain\Media\Services;

use App\Domain\Media\Contracts\MediaProcessor;
use App\Domain\Media\Contracts\MediaScanner;
use App\Domain\Media\Enums\MediaFailureReason;
use App\Domain\Media\Enums\MediaStatus;
use App\Domain\Media\Events\MediaFailed;
use App\Domain\Media\Events\MediaReady;
use App\Domain\Media\Events\MediaRetriesExhausted;
use App\Domain\Media\Exceptions\InsufficientTempSpace;
use App\Domain\Media\Exceptions\MediaRejected;
use App\Domain\Media\Jobs\PurgeQuarantineObjectJob;
use App\Domain\Media\Models\Media;
use App\Domain\Media\Support\MediaPaths;
use App\Domain\Media\Support\ProcessedMedia;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * The body of ProcessMediaJob (specs/10 §3 steps a–k). Safe to run twice: a row that is no longer
 * `uploaded`/`processing` is left alone, and variants are rewritten under the same keys.
 */
class MediaProcessingService
{
    public function __construct(
        private readonly MediaProcessor $processor,
        private readonly MediaScanner $scanner,
    ) {}

    /**
     * @throws InsufficientTempSpace when the worker should release the job and try later
     */
    public function process(int $mediaId): void
    {
        $media = Media::query()->find($mediaId);

        if ($media === null || ! in_array($media->status, [MediaStatus::Uploaded, MediaStatus::Processing], true)) {
            return;
        }

        // Conditional, so a row the sweeper claimed (`deleting`) in the meantime stays claimed.
        // Every run counts toward media:retry-failed's cap, the job's own retries included.
        $claimed = Media::query()->whereKey($media->id)
            ->whereIn('status', [MediaStatus::Uploaded, MediaStatus::Processing])
            ->update(['status' => MediaStatus::Processing, 'processing_attempts' => DB::raw('processing_attempts + 1')]);

        if ($claimed === 0) {
            return;
        }

        $media->refresh();
        $disk = Storage::disk($media->disk);

        try {
            $this->checkObject($disk, $media);
            $this->ensureFreeSpace($media->size_bytes);

            $local = $this->download($disk, $media);

            if (! $this->scanner->isClean($local)) {
                throw MediaRejected::suspicious('scanner flagged the file');
            }

            $result = $this->processor->process($local, $media->collection);
            $this->store($disk, $media, $result, (string) hash_file('sha256', $local));
        } catch (MediaRejected $rejected) {
            $this->reject($disk, $media, $rejected);
        } finally {
            $this->cleanTempDir($media->ulid);
        }
    }

    /**
     * Called when the job gave up (retries exhausted or timed out). The original stays in
     * quarantine so a later retry can pick it up.
     */
    public function giveUp(int $mediaId): void
    {
        $media = Media::query()->find($mediaId);

        if ($media === null) {
            return;
        }

        $this->cleanTempDir($media->ulid);

        if (! in_array($media->status, [MediaStatus::Uploaded, MediaStatus::Processing], true)) {
            return;
        }

        $this->markFailed($media, MediaStatus::Failed, MediaFailureReason::ProcessingError);

        if ($media->processing_attempts >= (int) config('media.lifecycle.retry_max_attempts')) {
            Log::warning('media.retry_exhausted', ['media' => $media->ulid, 'attempts' => $media->processing_attempts]);
            MediaRetriesExhausted::dispatch($media->ulid, $media->user_id, $media->collection);
        }
    }

    public function tempDir(string $ulid): string
    {
        return rtrim((string) config('media.processing.temp_dir'), '/').'/'.$ulid;
    }

    /**
     * HEAD before download: the object exists and has the declared size (specs/10 §3 a). The
     * presigned PUT cannot enforce size, so this is where the collection limit holds.
     */
    private function checkObject(Filesystem $disk, Media $media): void
    {
        if (! $disk->exists($media->path)) {
            throw MediaRejected::because(MediaFailureReason::ObjectMissing, $media->path);
        }

        $size = $disk->size($media->path);

        if ($size !== $media->size_bytes) {
            throw MediaRejected::because(MediaFailureReason::SizeMismatch, "declared {$media->size_bytes}, stored {$size}");
        }
    }

    private function ensureFreeSpace(int $bytes): void
    {
        $root = (string) config('media.processing.temp_dir');
        File::ensureDirectoryExists($root);

        $free = @disk_free_space($root);

        if ($free !== false && $free - $bytes < (int) config('media.processing.min_free_bytes')) {
            throw new InsufficientTempSpace("{$free} bytes free in {$root}");
        }
    }

    /**
     * Copies at most one byte more than declared, so a swapped object cannot fill the disk.
     */
    private function download(Filesystem $disk, Media $media): string
    {
        $dir = $this->tempDir($media->ulid);
        File::ensureDirectoryExists($dir);
        $local = $dir.'/original';

        $in = $disk->readStream($media->path);
        $out = fopen($local, 'wb');

        if ($in === null || $out === false) {
            throw MediaRejected::because(MediaFailureReason::ObjectMissing, 'unreadable object');
        }

        try {
            $copied = stream_copy_to_stream($in, $out, $media->size_bytes + 1);
        } finally {
            fclose($in);
            fclose($out);
        }

        if ($copied !== $media->size_bytes) {
            throw MediaRejected::because(MediaFailureReason::SizeMismatch, "downloaded {$copied} bytes");
        }

        return $local;
    }

    private function store(Filesystem $disk, Media $media, ProcessedMedia $result, string $checksum): void
    {
        $written = [];

        foreach ($result->variants as $variant) {
            $path = MediaPaths::variant($media->collection, $media->ulid, $variant->name, $variant->extension);

            $disk->put($path, $variant->contents, [
                'ContentType' => $variant->mimeType,
                'CacheControl' => (string) config("media.{$media->visibility->value}_cache_control"),
            ]);

            $written[] = [
                'variant' => $variant->name,
                'path' => $path,
                'width' => $variant->width,
                'height' => $variant->height,
                'size_bytes' => strlen($variant->contents),
                'mime_type' => $variant->mimeType,
            ];
        }

        DB::transaction(function () use ($media, $result, $checksum, $written): void {
            $media->variants()->delete();
            $media->variants()->createMany($written);

            $media->forceFill([
                'status' => MediaStatus::Ready,
                'failure_reason' => null,
                'mime_type' => $result->mimeType,
                'extension' => $result->extension,
                'width' => $result->width,
                'height' => $result->height,
                'checksum_sha256' => $checksum,
                'processed_at' => Date::now(),
            ])->save();
        });

        $this->deleteOriginal($disk, $media);
        $this->recheckQuarantineKey($media);

        MediaReady::dispatch($media->ulid, $media->user_id, $media->collection);
    }

    /**
     * Deterministic failures drop the original; suspicious files keep it for 30 days of review
     * (specs/10 §4, §9). Flagging the uploader for moderation arrives with P3-06.
     */
    private function reject(Filesystem $disk, Media $media, MediaRejected $rejected): void
    {
        if ($rejected->suspicious) {
            Log::warning('media.quarantined', [
                'media' => $media->ulid,
                'user_id' => $media->user_id,
                'detail' => $rejected->detail,
            ]);

            $this->markFailed($media, MediaStatus::Quarantined, $rejected->reason);

            return;
        }

        Log::info('media.rejected', ['media' => $media->ulid, 'reason' => $rejected->reason->value, 'detail' => $rejected->detail]);

        $this->markFailed($media, MediaStatus::Failed, $rejected->reason);
        $this->deleteOriginal($disk, $media);
        $this->recheckQuarantineKey($media);
    }

    /**
     * The presigned PUT outlives processing; delete the key again once it can no longer be written.
     */
    private function recheckQuarantineKey(Media $media): void
    {
        $at = $media->created_at->addSeconds((int) config('media.intent_ttl') + (int) config('media.quarantine_recheck_margin'));

        PurgeQuarantineObjectJob::dispatch($media->id)->delay($at->isFuture() ? $at : null);
    }

    private function markFailed(Media $media, MediaStatus $status, MediaFailureReason $reason): void
    {
        $media->forceFill(['status' => $status, 'failure_reason' => $reason])->save();

        MediaFailed::dispatch($media->ulid, $media->user_id, $media->collection, $status, $reason);
    }

    private function deleteOriginal(Filesystem $disk, Media $media): void
    {
        try {
            $disk->delete($media->path);
        } catch (Throwable $e) {
            // The reconcile job removes leftovers; the media row is already correct.
            Log::warning('media.original_not_deleted', ['media' => $media->ulid, 'error' => $e->getMessage()]);
        }
    }

    private function cleanTempDir(string $ulid): void
    {
        File::deleteDirectory($this->tempDir($ulid));
    }
}
