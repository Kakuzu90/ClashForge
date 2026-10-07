<?php

namespace App\Domain\Media\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Media processing time for System Health (specs/20 §6): the p95 of the latest run of each upload
 * processed in the window, from claim to ready. Null with nothing processed.
 */
#[TypeScript]
class MediaProcessingStatsData extends Data
{
    public function __construct(
        public int $windowHours,
        public int $processed,
        public ?int $p95Seconds,
        public int $alertSeconds,
        public bool $overAlert,
    ) {}
}
