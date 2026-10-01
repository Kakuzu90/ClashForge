<?php

namespace App\Domain\Media\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Media storage for the admin dashboard (FR-ADMIN-5). Totals include soft-deleted media, whose
 * objects stay in the bucket until `media:purge-deleted` (specs/10 §9); that part is also given on
 * its own. Collections list the rest, every collection present. Quarantined media is never in the
 * purge part, and `quarantinedCount` counts it deleted parent or not.
 */
#[TypeScript]
class MediaStorageData extends Data
{
    /**
     * @param  list<MediaCollectionUsageData>  $collections
     */
    public function __construct(
        public int $totalBytes,
        public int $totalObjects,
        public array $collections,
        public int $awaitingPurgeBytes,
        public int $awaitingPurgeObjects,
        public int $purgeAfterDays,
        public int $quarantinedCount,
    ) {}
}
