<?php

namespace App\Domain\Operations\Enums;

use App\Support\Enums\Concerns\EnumHelpers;
use App\Support\Enums\Contracts\HasLabelAndColor;

/**
 * The scheduler from its heartbeat (specs/20 §6): `Unknown` when none is recorded, which is not
 * the same as stopped.
 */
enum SchedulerState: string implements HasLabelAndColor
{
    use EnumHelpers;

    case Running = 'running';
    case Stopped = 'stopped';
    case Unknown = 'unknown';

    public function label(): string
    {
        return match ($this) {
            self::Running => 'Running',
            self::Stopped => 'Stopped',
            self::Unknown => 'No heartbeat recorded',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Running => 'state-success',
            self::Stopped => 'state-danger',
            self::Unknown => 'state-warning',
        };
    }
}
