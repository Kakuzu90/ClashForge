<?php

use App\Domain\PlayerAccounts\Data\ProgressionGroupData;
use App\Domain\PlayerAccounts\Support\ProgressionGrid;

// The account page's grids (specs/18 §6, P2-04 Q3): catalogue order, unlisted units last, super
// troops only while active, guardians never, Builder Base apart in the API's order.

function unitRow(string $name, int $level = 5, ?int $max = 10, string $village = 'home', bool $super = false): array
{
    return ['name' => $name, 'level' => $level, 'max_level' => $max, 'village' => $village, 'super_troop_active' => $super];
}

/**
 * @param  list<ProgressionGroupData>  $groups
 * @return array<string, list<string>>
 */
function gridNames(array $groups): array
{
    return collect($groups)->mapWithKeys(fn (ProgressionGroupData $g): array => [$g->key => array_map(fn ($u) => $u->name, $g->units)])->all();
}

it('orders each group by the catalogue, with units it does not list last, alphabetically', function () {
    $groups = app(ProgressionGrid::class)->groups(
        heroes: [unitRow('Royal Champion'), unitRow('Barbarian King'), unitRow('Battle Machine', village: 'builderBase')],
        equipment: [unitRow('Giant Arrow'), unitRow('Rage Vial'), unitRow('Barbarian Puppet')],
        troops: [
            unitRow('Zappy Newcomer'), unitRow('Minion'), unitRow('P.E.K.K.A'), unitRow('Barbarian'), unitRow('Alpha Newcomer'),
            unitRow('L.A.S.S.I'), unitRow('Wall Wrecker'), unitRow('Mighty Yak'),
            unitRow('Super Barbarian', super: true), unitRow('Sneaky Goblin', super: false), unitRow('Ice Hound', super: true),
            unitRow('Raged Barbarian', village: 'builderBase'), unitRow('Baby Dragon', village: 'builderBase'),
        ],
        spells: [unitRow('Poison Spell'), unitRow('Healing Spell'), unitRow('Lightning Spell')],
    );

    expect(gridNames($groups))->toBe([
        'heroes' => ['Barbarian King', 'Royal Champion'],
        'equipment' => ['Barbarian Puppet', 'Rage Vial', 'Giant Arrow'],
        'pets' => ['L.A.S.S.I', 'Mighty Yak'],
        'troops' => ['Barbarian', 'P.E.K.K.A', 'Minion', 'Alpha Newcomer', 'Zappy Newcomer'],
        'super' => ['Super Barbarian', 'Ice Hound'],
        'siege' => ['Wall Wrecker'],
        'spells' => ['Lightning Spell', 'Healing Spell', 'Poison Spell'],
        'builder' => ['Battle Machine', 'Raged Barbarian', 'Baby Dragon'],
    ]);
});

it('leaves out empty groups and malformed rows', function () {
    $groups = app(ProgressionGrid::class)->groups([], [], [unitRow('Barbarian'), ['name' => 'No level'], ['level' => 3]], []);

    expect(gridNames($groups))->toBe(['troops' => ['Barbarian']]);
});

it('marks maxed units and keeps a missing max level unknown', function () {
    $units = app(ProgressionGrid::class)->groups([], [], [unitRow('Barbarian', 12, 12), unitRow('Archer', 9, 12), unitRow('Giant', 4, null)], [])[0]->units;

    expect(array_map(fn ($u) => [$u->level, $u->maxLevel, $u->maxed], $units))->toBe([[12, 12, true], [9, 12, false], [4, null, false]]);
});

it('resolves a unit the pack does not have to its named placeholder (specs/23 §5)', function () {
    $unit = app(ProgressionGrid::class)->groups([], [], [unitRow('Brand New Troop')], [])[0]->units[0];

    expect($unit->asset->url)->toBeNull()
        ->and($unit->asset->alt)->toBe('Brand New Troop')
        ->and($unit->asset->short)->not->toBe('');
});
