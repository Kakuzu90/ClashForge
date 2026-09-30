<?php

namespace App\Domain\Media\Services;

use App\Domain\Media\Enums\MediaFailureReason;
use App\Domain\Media\Enums\MediaStatus;
use App\Domain\Media\Models\Media;
use App\Domain\Media\Models\MediaStorageOrphan;
use App\Domain\Media\Models\MediaVariant;
use App\Domain\Media\Support\ReconcileReport;
use Carbon\CarbonImmutable;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use League\Flysystem\StorageAttributes;

/**
 * Diffs the bucket against `media` + `media_variants` (specs/10 §9). A key with no row is logged
 * on first sight and deleted only when the next run finds it again, because an upload that is
 * still processing looks exactly like an orphan.
 */
class StorageReconciler
{
    /**
     * The only prefixes this job may list or delete under. It is an allowlist on purpose: game
     * assets under `game/` have no media rows by design (specs/10 §11.3), so "everything except
     * game/" would delete the whole asset pack the day a new prefix appeared.
     */
    public const PREFIXES = ['public/', 'quarantine/', 'private/'];

    private const LOOKUP_CHUNK = 500;

    public function reconcile(bool $dryRun = false): ReconcileReport
    {
        $disk = $this->disk();
        $startedAt = Date::now();
        $scanned = $flagged = $deleted = 0;
        $chunk = [];

        foreach (self::PREFIXES as $prefix) {
            foreach ($this->keysUnder($disk, $prefix) as $key) {
                $scanned++;
                $chunk[] = $key;

                if (count($chunk) === self::LOOKUP_CHUNK) {
                    [$f, $d] = $this->handleChunk($disk, $chunk, $startedAt, $dryRun);
                    $flagged += $f;
                    $deleted += $d;
                    $chunk = [];
                }
            }
        }

        if ($chunk !== []) {
            [$f, $d] = $this->handleChunk($disk, $chunk, $startedAt, $dryRun);
            $flagged += $f;
            $deleted += $d;
        }

        if (! $dryRun) {
            // Detection must be consecutive: a key that vanished or gained a row starts over.
            MediaStorageOrphan::query()->where('last_seen_at', '<', $startedAt)->delete();
        }

        return new ReconcileReport($scanned, $flagged, $deleted, $this->reportMissingObjects($disk));
    }

    /**
     * @return iterable<string>
     */
    private function keysUnder(FilesystemAdapter $disk, string $prefix): iterable
    {
        foreach ($disk->getDriver()->listContents(rtrim($prefix, '/'), true) as $item) {
            /** @var StorageAttributes $item */
            $path = $item->path();

            // Belt and braces: never act on a key outside the allowlisted prefix.
            if ($item->isFile() && str_starts_with($path, $prefix)) {
                yield $path;
            }
        }
    }

    /**
     * @param  list<string>  $keys
     * @return array{int, int} flagged, deleted
     */
    private function handleChunk(FilesystemAdapter $disk, array $keys, CarbonImmutable $now, bool $dryRun): array
    {
        $known = array_flip([
            ...Media::withTrashed()->whereIn('path', $keys)->pluck('path')->all(),
            ...MediaVariant::query()->whereIn('path', $keys)->pluck('path')->all(),
        ]);
        $orphans = array_values(array_filter($keys, fn (string $key) => ! isset($known[$key])));

        if ($orphans === []) {
            return [0, 0];
        }

        $seen = MediaStorageOrphan::query()->whereIn('path', $orphans)->get()->keyBy('path');
        $confirmBefore = $now->subHours((int) config('media.lifecycle.reconcile_confirm_after_hours'));
        $flagged = $deleted = 0;

        foreach ($orphans as $key) {
            $state = $seen->get($key);

            if ($state === null) {
                Log::info('media.reconcile.orphan_found', ['path' => $key]);
                $flagged++;

                if (! $dryRun) {
                    MediaStorageOrphan::query()->create(['path' => $key, 'first_seen_at' => $now, 'last_seen_at' => $now]);
                }

                continue;
            }

            if ($state->first_seen_at->greaterThan($confirmBefore)) {
                if (! $dryRun) {
                    $state->forceFill(['last_seen_at' => $now])->save();
                }

                continue;
            }

            Log::warning('media.reconcile.orphan_deleted', ['path' => $key, 'first_seen_at' => $state->first_seen_at->toIso8601String(), 'dry_run' => $dryRun]);
            $deleted++;

            if (! $dryRun) {
                $disk->delete($key);
                $state->delete();
            }
        }

        return [$flagged, $deleted];
    }

    /**
     * Rows whose objects should exist but do not: every variant of ready media, and the kept
     * original of quarantined and processing-error media. Transient states are skipped.
     */
    private function reportMissingObjects(FilesystemAdapter $disk): int
    {
        $missing = 0;

        $query = Media::query()->with('variants')->where(fn ($q) => $q
            ->whereIn('status', [MediaStatus::Ready, MediaStatus::Quarantined])
            ->orWhere(fn ($q) => $q->where('status', MediaStatus::Failed)->where('failure_reason', MediaFailureReason::ProcessingError)));

        foreach ($query->lazyById(self::LOOKUP_CHUNK) as $media) {
            $paths = $media->status === MediaStatus::Ready
                ? $media->variants->pluck('path')->all()
                : [$media->path];

            foreach ($paths as $path) {
                if (! $disk->exists($path)) {
                    Log::warning('media.reconcile.object_missing', ['media' => $media->ulid, 'path' => $path]);
                    $missing++;
                }
            }
        }

        return $missing;
    }

    private function disk(): FilesystemAdapter
    {
        /** @var FilesystemAdapter */
        return Storage::disk((string) config('media.disk'));
    }
}
