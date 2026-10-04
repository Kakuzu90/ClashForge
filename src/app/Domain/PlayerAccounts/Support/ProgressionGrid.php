<?php

namespace App\Domain\PlayerAccounts\Support;

use App\Domain\GameAssets\Enums\Village;
use App\Domain\GameAssets\Services\GameAssetCatalogue;
use App\Domain\GameAssets\Services\GameAssetResolver;
use App\Domain\PlayerAccounts\Data\ProgressionGroupData;
use App\Domain\PlayerAccounts\Data\ProgressionUnitData;
use Illuminate\Support\Str;

/**
 * The account page's progression grids (specs/18 §6), from the stored unit lists (specs/07: the
 * API shape in our keys), grouped as the game's profile screen shows them. Home Village units
 * follow the catalogue order of `config/assets.php`; a unit the catalogue does not list (a new
 * troop after a game update) comes last in its run, alphabetically, with a placeholder if the pack
 * has no image (specs/23 §5). Catalogue units the account has not unlocked are listed too, locked,
 * in their catalogue place, as the game shows them. Super troops (active or not) and guardians
 * are left out. Each hero carries its own equipment; equipment the
 * catalogue gives no hero gets a group of its own. Builder Base heroes and troops follow the
 * `bb_heroes` and `bb_units` lists the same way, locked ones included.
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

        $homeHeroes = $this->withLocked($homeHeroes, 'heroes');
        $homeSpells = $this->withLocked($homeSpells, 'spells');
        $homeTroops = $this->withLocked($this->withLocked($this->withLocked($homeTroops, 'units'), 'pets'), 'siege-machines');

        $pets = $this->catalogue->positions('pets');
        $siege = $this->catalogue->positions('siege-machines');
        $kind = fn (array $unit): string => match (true) {
            isset($pets[$this->slug($unit)]) => 'pets',
            isset($siege[$this->slug($unit)]) => 'siege',
            $this->catalogue->excluded($this->name($unit)) => 'hidden',
            default => 'troops',
        };
        $byKind = fn (string $wanted): array => array_values(array_filter($homeTroops, fn (array $u): bool => $kind($u) === $wanted));

        $equipment = $this->equipmentByHero($this->ordered($homeEquipment, 'heroes_equipments'));
        $heroSlugs = array_map($this->slug(...), array_filter($homeHeroes, fn (array $hero): bool => ! $this->locked($hero)));
        $unowned = array_merge([], ...array_values(array_diff_key($equipment, array_flip($heroSlugs))));

        $groups = [
            ['heroes', 'Heroes', Village::Home, $this->ordered($homeHeroes, 'heroes')],
            ['equipment', 'Other equipment', Village::Home, $unowned],
            ['pets', 'Pets', Village::Home, $this->ordered($byKind('pets'), 'pets')],
            ['troops', 'Troops', Village::Home, $this->ordered($byKind('troops'), 'units')],
            ['siege', 'Siege machines', Village::Home, $this->ordered($byKind('siege'), 'siege-machines')],
            ['spells', 'Spells', Village::Home, $this->ordered($homeSpells, 'spells')],
            ['builder_heroes', 'Heroes', Village::Builder, $this->ordered($this->withLocked($builderHeroes, 'bb_heroes', Village::Builder), 'bb_heroes')],
            ['builder_troops', 'Troops', Village::Builder, $this->ordered($this->withLocked($builderTroops, 'bb_units', Village::Builder), 'bb_units')],
        ];

        $out = [];
        foreach ($groups as [$key, $label, $village, $units]) {
            if ($units !== []) {
                $out[] = new ProgressionGroupData($key, $label, $village, array_map(
                    fn (array $u): ProgressionUnitData => $this->unit($u, $village, $key === 'heroes' && ! $this->locked($u) ? $this->heroEquipment($u, $equipment) : []),
                    $units,
                ));
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
     * Adds a locked row for every unit of a catalogue list the account does not have.
     *
     * @param  list<array<string, mixed>>  $units
     * @return list<array<string, mixed>>
     */
    private function withLocked(array $units, string $list, Village $village = Village::Home): array
    {
        $have = array_flip(array_map($this->slug(...), $units));

        foreach (array_keys($this->catalogue->positions($list)) as $slug) {
            if (! isset($have[$slug])) {
                $units[] = $this->lockedRow($slug, $village);
            }
        }

        return $units;
    }

    /**
     * @return array<string, mixed>
     */
    private function lockedRow(string $slug, Village $village = Village::Home): array
    {
        return ['name' => $this->assets->unitName($slug, $village) ?? Str::headline($slug), 'level' => 0, 'locked' => true];
    }

    /**
     * @param  array<string, mixed>  $unit
     */
    private function locked(array $unit): bool
    {
        return ($unit['locked'] ?? false) === true;
    }

    /**
     * A hero's equipment in catalogue order, the pieces it has not unlocked locked.
     *
     * @param  array<string, mixed>  $hero
     * @param  array<string, list<array<string, mixed>>>  $byHero
     * @return list<array<string, mixed>>
     */
    private function heroEquipment(array $hero, array $byHero): array
    {
        $slug = $this->slug($hero);
        $owned = $byHero[$slug] ?? [];
        $have = array_flip(array_map($this->slug(...), $owned));

        foreach ((array) config("assets.heroes_equipments.{$slug}") as $item) {
            if (is_string($item) && ! isset($have[$item])) {
                $owned[] = $this->lockedRow($item);
            }
        }

        return $this->ordered($owned, 'heroes_equipments');
    }

    /**
     * Hero slug → its equipment rows, in catalogue order; `''` holds the ones no hero lists. Rows
     * whose hero the account does not have go to the "Other equipment" group with them.
     *
     * @param  list<array<string, mixed>>  $equipment
     * @return array<string, list<array<string, mixed>>>
     */
    private function equipmentByHero(array $equipment): array
    {
        $owners = [];
        foreach ((array) config('assets.heroes_equipments') as $hero => $slugs) {
            foreach ((array) $slugs as $slug) {
                $owners[$slug] = (string) $hero;
            }
        }

        $byHero = [];
        foreach ($equipment as $item) {
            $byHero[$owners[$this->slug($item)] ?? ''][] = $item;
        }

        return $byHero;
    }

    /**
     * @param  array<string, mixed>  $unit
     * @param  list<array<string, mixed>>  $equipment
     */
    private function unit(array $unit, Village $village, array $equipment = []): ProgressionUnitData
    {
        $level = (int) $unit['level'];
        $max = is_int($unit['max_level'] ?? null) ? $unit['max_level'] : null;
        $locked = $this->locked($unit);

        return new ProgressionUnitData(
            name: $this->name($unit),
            asset: $this->assets->unit($this->name($unit), $village),
            level: $level,
            maxLevel: $max,
            maxed: ! $locked && $max !== null && $level >= $max,
            locked: $locked,
            equipment: array_map(fn (array $item): ProgressionUnitData => $this->unit($item, $village), $equipment),
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
