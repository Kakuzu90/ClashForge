<?php

namespace App\Domain\PlayerAccounts\Support;

use App\Domain\GameAssets\Enums\Village;
use App\Domain\GameAssets\Services\GameAssetCatalogue;
use App\Domain\GameAssets\Services\GameAssetResolver;
use App\Domain\PlayerAccounts\Data\ProgressionGroupData;
use App\Domain\PlayerAccounts\Data\ProgressionUnitData;

/**
 * The account page's progression grids (specs/18 §6), from the stored unit lists (specs/07: the
 * API shape in our keys). Home Village units follow the catalogue order of `config/assets.php`;
 * a unit the catalogue does not list (a new troop after a game update) comes last in its group,
 * alphabetically, with a placeholder if the pack has no image (specs/23 §5). Super troops show only
 * while active, in a group after the troops; guardians not at all. Builder Base units are one
 * group in the API's own order, which is the game's, as the catalogue has no Builder Base lists.
 */
final class ProgressionGrid
{
    public function __construct(
        private readonly GameAssetCatalogue $catalogue,
        private readonly GameAssetResolver $assets,
    ) {}

    /**
     * @param  list<array<string, mixed>>  $heroes
     * @param  list<array<string, mixed>>  $equipment
     * @param  list<array<string, mixed>>  $troops
     * @param  list<array<string, mixed>>  $spells
     * @return list<ProgressionGroupData>
     */
    public function groups(array $heroes, array $equipment, array $troops, array $spells): array
    {
        [$homeHeroes, $builderHeroes] = $this->split($heroes);
        [$homeTroops, $builderTroops] = $this->split($troops);
        [$homeSpells] = $this->split($spells);
        [$homeEquipment] = $this->split($equipment);

        $pets = $this->catalogue->positions('pets');
        $siege = $this->catalogue->positions('siege-machines');
        $kind = fn (array $unit): string => match (true) {
            isset($pets[$this->slug($unit)]) => 'pets',
            isset($siege[$this->slug($unit)]) => 'siege',
            $this->catalogue->excluded($this->name($unit)) => ($unit['super_troop_active'] ?? false) === true ? 'super' : 'hidden',
            default => 'troops',
        };
        $byKind = fn (string $wanted): array => array_values(array_filter($homeTroops, fn (array $u): bool => $kind($u) === $wanted));

        $groups = [
            ['heroes', 'Heroes', $this->ordered($homeHeroes, 'heroes')],
            ['equipment', 'Hero equipment', $this->ordered($homeEquipment, 'heroes_equipments')],
            ['pets', 'Pets', $this->ordered($byKind('pets'), 'pets')],
            ['troops', 'Troops', $this->ordered($byKind('troops'), 'units')],
            ['super', 'Active super troops', $this->ordered($byKind('super'), 'excluded_units')],
            ['siege', 'Siege machines', $this->ordered($byKind('siege'), 'siege-machines')],
            ['spells', 'Spells', $this->ordered($homeSpells, 'spells')],
            ['builder', Village::Builder->label(), [...$builderHeroes, ...$builderTroops]],
        ];

        $out = [];
        foreach ($groups as [$key, $label, $units]) {
            if ($units !== []) {
                $village = $key === 'builder' ? Village::Builder : Village::Home;
                $out[] = new ProgressionGroupData($key, $label, array_map(fn (array $u): ProgressionUnitData => $this->unit($u, $village), $units));
            }
        }

        return $out;
    }

    /**
     * @param  list<array<string, mixed>>  $units
     * @return array{0: list<array<string, mixed>>, 1: list<array<string, mixed>>}
     */
    private function split(array $units): array
    {
        $home = $builder = [];
        foreach ($units as $unit) {
            if (! is_string($unit['name'] ?? null) || ! is_int($unit['level'] ?? null)) {
                continue;
            }
            ($unit['village'] ?? null) === Village::Builder->value ? $builder[] = $unit : $home[] = $unit;
        }

        return [$home, $builder];
    }

    /**
     * Catalogue order first, then the units the list does not have, alphabetically.
     *
     * @param  list<array<string, mixed>>  $units
     * @return list<array<string, mixed>>
     */
    private function ordered(array $units, string $list): array
    {
        $positions = $this->catalogue->positions($list);

        usort($units, function (array $a, array $b) use ($positions): int {
            $pa = $positions[$this->slug($a)] ?? PHP_INT_MAX;
            $pb = $positions[$this->slug($b)] ?? PHP_INT_MAX;

            return $pa <=> $pb ?: strcasecmp($this->name($a), $this->name($b));
        });

        return $units;
    }

    /**
     * @param  array<string, mixed>  $unit
     */
    private function unit(array $unit, Village $village): ProgressionUnitData
    {
        $level = (int) $unit['level'];
        $max = is_int($unit['max_level'] ?? null) ? $unit['max_level'] : null;

        return new ProgressionUnitData(
            name: $this->name($unit),
            asset: $this->assets->unit($this->name($unit), $village),
            level: $level,
            maxLevel: $max,
            maxed: $max !== null && $level >= $max,
        );
    }

    /**
     * @param  array<string, mixed>  $unit
     */
    private function name(array $unit): string
    {
        return is_string($unit['name'] ?? null) ? $unit['name'] : '';
    }

    /**
     * @param  array<string, mixed>  $unit
     */
    private function slug(array $unit): string
    {
        return $this->catalogue->slug($this->name($unit));
    }
}
