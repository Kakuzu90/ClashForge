<?php

namespace App\Domain\Bases\Enums;

use App\Support\Enums\Concerns\EnumHelpers;
use App\Support\Enums\Contracts\HasLabelAndColor;

/**
 * What a base is built for (FR-BASE-3); exactly one per base.
 */
enum BaseCategory: string implements HasLabelAndColor
{
    use EnumHelpers;

    case War = 'war';
    case Cwl = 'cwl';
    case Farming = 'farming';
    case Trophy = 'trophy';
    case Legend = 'legend';
    case Anti3Star = 'anti_3_star';
    case Anti2Star = 'anti_2_star';
    case Hybrid = 'hybrid';
    case Progress = 'progress';
    case Troll = 'troll';

    public function label(): string
    {
        return match ($this) {
            self::War => 'War',
            self::Cwl => 'CWL',
            self::Farming => 'Farming',
            self::Trophy => 'Trophy',
            self::Legend => 'Legend League',
            self::Anti3Star => 'Anti-3-Star',
            self::Anti2Star => 'Anti-2-Star',
            self::Hybrid => 'Hybrid',
            self::Progress => 'Progress Base',
            self::Troll => 'Funny/Troll',
        };
    }

    public function color(): string
    {
        return 'text-muted';
    }
}
