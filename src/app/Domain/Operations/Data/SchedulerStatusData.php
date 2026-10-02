<?php

namespace App\Domain\Operations\Data;

use App\Domain\Operations\Enums\SchedulerState;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * When the scheduler last ran `platform:heartbeat` (every minute), and the age past which it
 * counts as stopped (specs/20 §6).
 */
#[TypeScript]
class SchedulerStatusData extends Data
{
    public function __construct(
        public SchedulerState $state,
        public ?string $lastBeatAt,
        public ?int $ageSeconds,
        public int $maxAgeSeconds,
    ) {}
}
