<?php

namespace App\Domain\GameAssets\Enums;

use App\Support\Enums\Concerns\EnumHelpers;
use App\Support\Enums\Contracts\HasLabelAndColor;

/**
 * Manifest categories (specs/10 §11.2). Clan badges are referenced from the API, never packed.
 */
enum GameAssetCategory: string implements HasLabelAndColor
{
    use EnumHelpers;

    case Troop = 'troop';
    case Hero = 'hero';
    case Spell = 'spell';
    case Equipment = 'equipment';
    case TownHall = 'town_hall';
    case League = 'league';

    public function label(): string
    {
        return match ($this) {
            self::Troop => 'Troop',
            self::Hero => 'Hero',
            self::Spell => 'Spell',
            self::Equipment => 'Hero equipment',
            self::TownHall => 'Town Hall',
            self::League => 'League',
        };
    }

    public function color(): string
    {
        return 'text-muted';
    }

    public function isUnit(): bool
    {
        return in_array($this, [self::Troop, self::Hero, self::Spell, self::Equipment], true);
    }

    /**
     * The pack folder each category lives in (specs/10 §2).
     */
    public function folder(): string
    {
        return match ($this) {
            self::TownHall => 'townhalls',
            self::League => 'leagues',
            default => 'units',
        };
    }
}
