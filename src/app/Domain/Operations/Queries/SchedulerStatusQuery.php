<?php

namespace App\Domain\Operations\Queries;

use App\Domain\Operations\Data\SchedulerStatusData;
use App\Domain\Operations\Enums\SchedulerState;
use App\Support\Health\HealthChecker;
use Illuminate\Support\Facades\Date;

/**
 * The scheduler heartbeat as `/health` reads it (`platform.health.heartbeat_max_age`).
 */
class SchedulerStatusQuery
{
    public function __construct(private readonly HealthChecker $health) {}

    public function status(): SchedulerStatusData
    {
        $beat = $this->health->lastHeartbeat();
        $maxAge = (int) config('platform.health.heartbeat_max_age');

        if ($beat === null) {
            return new SchedulerStatusData(SchedulerState::Unknown, null, null, $maxAge);
        }

        $age = max(0, Date::now()->getTimestamp() - $beat);

        return new SchedulerStatusData(
            state: $age > $maxAge ? SchedulerState::Stopped : SchedulerState::Running,
            lastBeatAt: Date::createFromTimestamp($beat)->toIso8601String(),
            ageSeconds: $age,
            maxAgeSeconds: $maxAge,
        );
    }
}
