<?php

namespace App\Domain\Media\Queries;

use App\Domain\Media\Data\MediaCollectionUsageData;
use App\Domain\Media\Data\MediaStorageData;
use App\Domain\Media\Enums\MediaCollection;
use App\Domain\Media\Enums\MediaStatus;
use Illuminate\Support\Facades\DB;

/**
 * What the media pipeline holds in the bucket, from the database (FR-ADMIN-5). Every row counts
 * except `pending`, whose size is only declared and whose object may never arrive. The `game/`
 * pack has no rows and is not counted (specs/10 §11). Quarantined media counts as held for review
 * whether or not its parent was deleted: nothing purges it before moderation does (P3-06). Not
 * cached: an admin view (specs/21 §3).
 */
class MediaStorageQuery
{
    public function storage(): MediaStorageData
    {
        // Purged after the window: soft-deleted, except quarantined media, which the purge never
        // selects (specs/10 §9) and which stays held for review.
        $purgeable = 'media.deleted_at IS NOT NULL AND media.status <> ?';
        $quarantined = [MediaStatus::Quarantined->value];

        $originals = DB::table('media')
            ->where('media.status', '<>', MediaStatus::Pending->value)
            ->selectRaw("media.collection AS collection, {$purgeable} AS deleted, COUNT(*) AS objects, COALESCE(SUM(media.size_bytes), 0) AS bytes", $quarantined)
            ->groupByRaw('1, 2')
            ->get();

        $variants = DB::table('media_variants')
            ->join('media', 'media.id', '=', 'media_variants.media_id')
            ->where('media.status', '<>', MediaStatus::Pending->value)
            ->selectRaw("media.collection AS collection, {$purgeable} AS deleted, COUNT(*) AS objects, COALESCE(SUM(media_variants.size_bytes), 0) AS bytes", $quarantined)
            ->groupByRaw('1, 2')
            ->get();

        $live = array_fill_keys(MediaCollection::values(), ['bytes' => 0, 'objects' => 0]);
        $purge = ['bytes' => 0, 'objects' => 0];

        foreach ($originals->concat($variants) as $row) {
            $bytes = (int) $row->bytes;
            $objects = (int) $row->objects;

            if ((bool) $row->deleted) {
                $purge['bytes'] += $bytes;
                $purge['objects'] += $objects;

                continue;
            }

            $live[$row->collection] ??= ['bytes' => 0, 'objects' => 0];
            $live[$row->collection]['bytes'] += $bytes;
            $live[$row->collection]['objects'] += $objects;
        }

        $collections = [];
        foreach ($live as $collection => $usage) {
            $collections[] = new MediaCollectionUsageData(
                collection: $collection,
                label: MediaCollection::tryFrom($collection)?->label() ?? $collection,
                bytes: $usage['bytes'],
                objects: $usage['objects'],
            );
        }

        return new MediaStorageData(
            totalBytes: array_sum(array_column($live, 'bytes')) + $purge['bytes'],
            totalObjects: array_sum(array_column($live, 'objects')) + $purge['objects'],
            collections: $collections,
            awaitingPurgeBytes: $purge['bytes'],
            awaitingPurgeObjects: $purge['objects'],
            purgeAfterDays: (int) config('media.lifecycle.purge_after_days'),
            quarantinedCount: DB::table('media')->where('status', MediaStatus::Quarantined->value)->count(),
        );
    }
}
