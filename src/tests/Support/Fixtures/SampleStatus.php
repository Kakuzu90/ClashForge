<?php

namespace Tests\Support\Fixtures;

use App\Support\Enums\Concerns\EnumHelpers;
use App\Support\Enums\Contracts\HasLabelAndColor;

enum SampleStatus: string implements HasLabelAndColor
{
    use EnumHelpers;

    case Active = 'active';
    case Suspended = 'suspended';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Suspended => 'Suspended',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Active => 'state-success',
            self::Suspended => 'state-danger',
        };
    }
}
