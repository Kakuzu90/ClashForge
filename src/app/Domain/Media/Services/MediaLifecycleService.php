<?php

namespace App\Domain\Media\Services;

use App\Domain\Media\Enums\MediaFailureReason;
use App\Domain\Media\Enums\MediaStatus;
use App\Domain\Media\Exceptions\ObjectsNotDeleted;
use App\Domain\Media\Jobs\DeleteMediaObjectsJob;
use App\Domain\Media\Jobs\ProcessMediaJob;
use App\Domain\Media\Models\Media;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * The scheduled cleanup of specs/10 §9. Rows are claimed by flipping them to `deleting` (which
 * attachment refuses) before their objects are queued for deletion. Quarantined media is never
 * selected: it is kept for moderator review.
 */
class MediaLifecycleService
{
    /** Account anonymisation; quarantine remains available for staff review. */
    public function purgeOwnedBy(int $userId): int
    {
        return $this->claim(fn () => Media::withTrashed()->where('user_id', $userId)
            ->whereNotIn('status', [MediaStatus::Quarantined, MediaStatus::Deleting]), false);
    }

    /**
     * Unattached media past its expiry: never completed, never processed, never attached, or
     * failed. `processing` rows are left to their job.
     */
    public function sweepOrphans(bool $dryRun = false): int
    {
        return $this->claim(fn () => Media::query()
            ->whereNull('attachable_id')
            ->where('expires_at', '<=', Date::now())
            ->whereIn('status', [MediaStatus::Pending, MediaStatus::Uploaded, MediaStatus::Ready, MediaStatus::Failed]), $dryRun);
    }

    /**
     * `deleting` rows whose deletion job was lost (worker killed, job failed for good).
     */
    public function requeueStalledDeletions(bool $dryRun = false): int
    {
        $cutoff = Date::now()->subMinutes((int) config('media.lifecycle.stale_deleting_minutes'));

        $requeued = $this->claim(fn () => Media::withTrashed()
            ->where('status', MediaStatus::Deleting)
            ->where('updated_at', '<=', $cutoff), $dryRun);

        // Deletions only stall when storage keeps refusing them: public objects outlive their
        // purge until someone looks, so this alerts every hour it persists.
        if ($requeued > 0) {
            Log::error('media.deletion_stalled', ['count' => $requeued, 'dry_run' => $dryRun]);
        }

        return $requeued;
    }

    /**
     * Soft-deleted media past the recovery window (FR-MEDIA-9).
     */
    public function purgeDeleted(bool $dryRun = false): int
    {
        $cutoff = Date::now()->subDays((int) config('media.lifecycle.purge_after_days'));

        return $this->claim(fn () => Media::onlyTrashed()
            ->where('deleted_at', '<=', $cutoff)
            ->whereNotIn('status', [MediaStatus::Quarantined, MediaStatus::Deleting]), $dryRun);
    }

    /**
     * Re-queues processing errors, the only failures that keep their original, while they are
     * young and have attempts left. Returns the number re-dispatched.
     */
    public function retryFailed(): int
    {
        $batch = (int) config('media.lifecycle.batch_size');
        $query = fn (): Builder => Media::query()
            ->where('status', MediaStatus::Failed)
            ->where('failure_reason', MediaFailureReason::ProcessingError)
            ->where('created_at', '>', Date::now()->subHours((int) config('media.lifecycle.retry_window_hours')))
            ->where('processing_attempts', '<', (int) config('media.lifecycle.retry_max_attempts'));

        $retried = 0;

        do {
            /** @var list<int> $ids */
            $ids = $query()->orderBy('id')->limit($batch)->pluck('id')->all();

            Media::query()->whereIn('id', $ids)->where('status', MediaStatus::Failed)
                ->update(['status' => MediaStatus::Uploaded, 'failure_reason' => null]);

            foreach ($ids as $id) {
                ProcessMediaJob::dispatch($id);
            }

            $retried += count($ids);
        } while (count($ids) === $batch);

        return $retried;
    }

    /**
     * The body of DeleteMediaObjectsJob: removes the original and every variant, then the row.
     * Only claimed (`deleting`) rows are touched, so a stray id can never delete live media.
     *
     * @param  list<int>  $mediaIds
     *
     * @throws ObjectsNotDeleted after the whole batch, when storage refused any delete; the
     *                           refused rows stay `deleting` and the job retries
     */
    public function deleteMedia(array $mediaIds): int
    {
        $deleted = 0;
        $refused = [];

        foreach (array_values(array_unique($mediaIds)) as $id) {
            try {
                $deleted += DB::transaction(fn (): int => $this->deleteOne($id));
            } catch (Throwable $e) {
                // One stubborn row must not hold back the rest of its batch.
                Log::warning('media.delete_refused', ['media_id' => $id, 'error' => $e->getMessage()]);
                $refused[] = $id;
            }
        }

        if ($refused !== []) {
            throw new ObjectsNotDeleted('media ids '.implode(', ', $refused));
        }

        return $deleted;
    }

    /**
     * Removes per-job temp directories a killed worker left behind (specs/10 §10). Run when the
     * media worker boots; the age guard spares a directory a live job is still using.
     */
    public function sweepTempDirs(bool $dryRun = false): int
    {
        $root = (string) config('media.processing.temp_dir');

        if (! File::isDirectory($root)) {
            return 0;
        }

        $cutoff = Date::now()->getTimestamp() - (int) config('queue.connections.media.retry_after');
        $removed = 0;

        foreach (File::directories($root) as $dir) {
            if (File::lastModified($dir) > $cutoff) {
                continue;
            }

            if (! $dryRun) {
                File::deleteDirectory($dir);
            }

            $removed++;
        }

        return $removed;
    }

    /**
     * Re-reads the row under a lock; only rows still claimed for deletion may be removed.
     */
    private function deleteOne(int $id): int
    {
        $media = Media::withTrashed()->with('variants')
            ->whereKey($id)
            ->where('status', MediaStatus::Deleting)
            ->lockForUpdate()
            ->first();

        if ($media === null) {
            return 0;
        }

        // Missing keys are not an error; a false return is a refused delete.
        if (! Storage::disk($media->disk)->delete([$media->path, ...$media->variants->pluck('path')->all()])) {
            throw new ObjectsNotDeleted("media {$media->ulid}");
        }

        $media->variants()->delete();
        $media->forceDelete();

        return 1;
    }

    /**
     * Flips matching rows to `deleting` in batches and queues one deletion job per batch.
     *
     * @param  Closure(): Builder<Media>  $query
     */
    private function claim(Closure $query, bool $dryRun): int
    {
        if ($dryRun) {
            return $query()->count();
        }

        $batch = (int) config('media.lifecycle.batch_size');
        $claimed = 0;

        do {
            /** @var list<int> $ids */
            $ids = DB::transaction(function () use ($query, $batch): array {
                $ids = $query()->orderBy('id')->limit($batch)->lockForUpdate()->pluck('id')->all();

                Media::withTrashed()->whereIn('id', $ids)->update(['status' => MediaStatus::Deleting]);

                return $ids;
            });

            if ($ids !== []) {
                DeleteMediaObjectsJob::dispatch($ids)->afterCommit();
            }

            $claimed += count($ids);
        } while (count($ids) === $batch);

        return $claimed;
    }
}
