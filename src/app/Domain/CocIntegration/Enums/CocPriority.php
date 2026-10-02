<?php

namespace App\Domain\CocIntegration\Enums;

use App\Support\Enums\Concerns\EnumHelpers;
use App\Support\Enums\Contracts\HasLabelAndColor;

/**
 * Who is waiting for the call (specs/09 §4). Interactive calls may use the whole rate budget;
 * background calls (sync) only their share, and they skip the cache read because they are due.
 */
enum CocPriority: string implements HasLabelAndColor
{
    use EnumHelpers;

    case Interactive = 'interactive';
    case Background = 'background';

    public function label(): string
    {
        return match ($this) {
            self::Interactive => 'Interactive',
            self::Background => 'Background',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Interactive => 'state-info',
            self::Background => 'text-muted',
        };
    }
}
