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
    case Pet = 'pet';
    case SiegeMachine = 'siege_machine';
    case TownHall = 'town_hall';
    case League = 'league';

    public function label(): string
    {
        return match ($this) {
            self::Troop => 'Troop',
            self::Hero => 'Hero',
            self::Spell => 'Spell',
            self::Equipment => 'Hero equipment',
            self::Pet => 'Pet',
            self::SiegeMachine => 'Siege machine',
            self::TownHall => 'Town Hall',
            self::League => 'League',
        };
    }

    public function color(): string
    {
        return 'text-muted';
    }

    /**
     * Everything the API lists in a player's heroes, troops, spells or heroEquipment, looked up by
     * its API name.
     */
    public function isUnit(): bool
    {
        return ! in_array($this, [self::TownHall, self::League], true);
    }

    /**
     * Units and Town Halls exist in both villages; the folder says which (specs/10 §11.1).
     */
    public function hasVillage(): bool
    {
        return $this !== self::League;
    }

    /**
     * The pack folder each category lives in (specs/10 §11.1). Builder Base assets sit one level
     * down, in `{folder}/builder-base/`.
     */
    public function folder(): string
    {
        return match ($this) {
            self::Troop => 'units',
            self::Hero => 'heroes',
            self::Spell => 'spells',
            self::Equipment => 'equipments',
            self::Pet => 'pets',
            self::SiegeMachine => 'machines',
            self::TownHall => 'townhalls',
            self::League => 'leagues',
        };
    }

    public static function fromFolder(string $folder): ?self
    {
        foreach (self::cases() as $category) {
            if ($category->folder() === $folder) {
                return $category;
            }
        }

        return null;
    }
}
