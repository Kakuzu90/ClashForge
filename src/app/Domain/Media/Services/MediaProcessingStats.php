<?php

namespace App\Domain\Media\Services;

use App\Domain\Media\Data\MediaProcessingStatsData;
use App\Domain\Media\Models\Media;
use Illuminate\Support\Facades\Date;

/**
 * The media processing p95 on System Health (specs/20 §6, `media.health`). Read on page load, not
 * cached (specs/21 §3); one window of rows is small enough to rank in PHP on either database.
 */
class MediaProcessingStats
{
    public function summary(): MediaProcessingStatsData
    {
        $hours = (int) config('media.health.processing_window_hours');
        $alert = (int) config('media.health.processing_p95_alert_seconds');

        $seconds = Media::withTrashed()
            ->where('processed_at', '>=', Date::now()->subHours($hours))
            ->whereNotNull('processing_started_at')
            ->toBase()
            ->get(['processing_started_at', 'processed_at'])
            ->map(fn (object $row): int => max(0, Date::parse($row->processed_at)->getTimestamp() - Date::parse($row->processing_started_at)->getTimestamp()))
            ->sort()
            ->values();

        // Nearest rank.
        $p95 = $seconds->isEmpty() ? null : (int) $seconds[(int) ceil(0.95 * $seconds->count()) - 1];

        return new MediaProcessingStatsData(
            windowHours: $hours,
            processed: $seconds->count(),
            p95Seconds: $p95,
            alertSeconds: $alert,
            overAlert: $p95 !== null && $p95 > $alert,
        );
    }
}
