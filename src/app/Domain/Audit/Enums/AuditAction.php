<?php

namespace App\Domain\Audit\Enums;

use App\Support\Enums\Concerns\EnumHelpers;
use App\Support\Enums\Contracts\HasLabelAndColor;

/**
 * What a privileged action did (specs/12 §9). Each task that audits something adds its case here,
 * so the admin filter always lists every action the log can hold.
 */
enum AuditAction: string implements HasLabelAndColor
{
    use EnumHelpers;

    case RoleChanged = 'role.changed';

    public function label(): string
    {
        return match ($this) {
            self::RoleChanged => 'Role changed',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::RoleChanged => 'state-warning',
        };
    }
}
