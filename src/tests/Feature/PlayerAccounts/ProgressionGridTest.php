<?php

use App\Domain\GameAssets\Enums\Village;
use App\Domain\PlayerAccounts\Data\ProgressionGroupData;
use App\Domain\PlayerAccounts\Support\ProgressionGrid;

// The account page's grids (specs/18 §6, P2-04 Q3): catalogue order, unlisted units last, super
// troops and guardians never, even while a super troop is active, equipment under its hero, Builder Base apart in the
// API's order.

function unitRow(string $name, int $level = 5, ?int $max = 10, string $village = 'home', bool $super = false): array
{
    return ['name' => $name, 'level' => $level, 'max_level' => $max, 'village' => $village, 'super_troop_active' => $super];
}

/**
 * The units the account has, per group; locked catalogue units left out.
 *
 * @param  list<ProgressionGroupData>  $groups
 * @return array<string, list<string>>
 */
function gridNames(array $groups): array
{
    return collect($groups)
        ->mapWithKeys(fn (ProgressionGroupData $g): array => [$g->key => array_values(array_map(fn ($u) => $u->name, array_filter($g->units, fn ($u) => ! $u->locked)))])
        ->filter()
        ->all();
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
        'equipment' => ['Giant Arrow'],
        'pets' => ['L.A.S.S.I', 'Mighty Yak'],
        'troops' => ['Barbarian', 'P.E.K.K.A', 'Minion', 'Alpha Newcomer', 'Zappy Newcomer'],
        'siege' => ['Wall Wrecker'],
        'spells' => ['Lightning Spell', 'Healing Spell', 'Poison Spell'],
        'builder_heroes' => ['Battle Machine'],
        'builder_troops' => ['Raged Barbarian', 'Baby Dragon'],
    ])->and(collect($groups)->mapWithKeys(fn (ProgressionGroupData $g): array => [$g->key => $g->village])->all())->toBe([
        'heroes' => Village::Home, 'equipment' => Village::Home, 'pets' => Village::Home, 'troops' => Village::Home,
        'siege' => Village::Home, 'spells' => Village::Home, 'builder_heroes' => Village::Builder, 'builder_troops' => Village::Builder,
    ]);
});

it('puts each hero\'s equipment under that hero, in catalogue order', function () {
    $heroes = app(ProgressionGrid::class)->groups(
        heroes: [unitRow('Archer Queen'), unitRow('Barbarian King')],
        equipment: [unitRow('Giant Arrow', 18, 18), unitRow('Rage Vial'), unitRow('Archer Puppet'), unitRow('Barbarian Puppet')],
        troops: [],
        spells: [],
    )[0]->units;

    $owned = fn ($hero) => array_values(array_filter($hero->equipment, fn ($item) => ! $item->locked));

    expect(array_map(fn ($hero) => [$hero->name, array_map(fn ($item) => $item->name, $owned($hero))], array_slice($heroes, 0, 2)))->toBe([
        ['Barbarian King', ['Barbarian Puppet', 'Rage Vial']],
        ['Archer Queen', ['Archer Puppet', 'Giant Arrow']],
    ])->and($owned($heroes[1])[1]->maxed)->toBeTrue()
        ->and($owned($heroes[1])[1]->equipment)->toBe([]);
});

/**
 * No catalogue lists, so no locked units: the groups hold only what the account has.
 */
function withoutCatalogue(): void
{
    config(['assets.heroes' => [], 'assets.units' => [], 'assets.spells' => [], 'assets.pets' => [], 'assets.siege-machines' => [], 'assets.bb_heroes' => [], 'assets.bb_units' => []]);
}

it('leaves out empty groups and malformed rows', function () {
    withoutCatalogue();
    $groups = app(ProgressionGrid::class)->groups([], [], [unitRow('Barbarian'), ['name' => 'No level'], ['level' => 3]], []);

    expect(collect($groups)->map(fn ($g) => [$g->key, count($g->units)])->all())->toBe([['troops', 1]]);
});

it('lists the catalogue units an account has not unlocked, locked, in catalogue order', function () {
    $groups = collect(app(ProgressionGrid::class)->groups(
        heroes: [unitRow('Archer Queen')],
        equipment: [unitRow('Giant Arrow')],
        troops: [unitRow('Archer'), unitRow('Wall Wrecker')],
        spells: [],
    ))->keyBy('key');
    $state = fn ($units) => array_map(fn ($u) => [$u->name, $u->locked, $u->level], array_slice($units, 0, 3));

    expect($state($groups['heroes']->units))->toBe([['Barbarian King', true, 0], ['Archer Queen', false, 5], ['Minion Prince', true, 0]])
        ->and($state($groups['troops']->units))->toBe([['Barbarian', true, 0], ['Archer', false, 5], ['Giant', true, 0]])
        ->and($state($groups['siege']->units))->toBe([['Wall Wrecker', false, 5], ['Battle Blimp', true, 0], ['Stone Slammer', true, 0]])
        ->and($groups['spells']->units[0]->locked)->toBeTrue()
        ->and($groups['heroes']->units[0]->equipment)->toBe([])
        ->and(array_map(fn ($u) => [$u->name, $u->locked], array_slice($groups['heroes']->units[1]->equipment, 0, 3)))
        ->toBe([['Archer Puppet', true], ['Invisibility Vial', true], ['Giant Arrow', false]])
        ->and(collect($groups)->flatMap(fn ($g) => $g->units)->filter->locked->every(fn ($u) => ! $u->maxed && $u->maxLevel === null))->toBeTrue();
});

it('orders Builder Base units by its own lists, with the ones not unlocked locked', function () {
    $groups = collect(app(ProgressionGrid::class)->groups(
        heroes: [unitRow('Battle Copter', village: 'builderBase')],
        equipment: [],
        troops: [unitRow('Baby Dragon', village: 'builderBase'), unitRow('Brand New Builder Troop', village: 'builderBase'), unitRow('Raged Barbarian', village: 'builderBase')],
        spells: [],
    ))->keyBy('key');
    $state = fn ($units) => array_map(fn ($u) => [$u->name, $u->locked], $units);

    expect($state($groups['builder_heroes']->units))->toBe([['Battle Machine', true], ['Battle Copter', false]])
        ->and(array_slice($state($groups['builder_troops']->units), 0, 6))->toBe([
            ['Raged Barbarian', false], ['Sneaky Archer', true], ['Boxer Giant', true], ['Beta Minion', true], ['Bomber', true], ['Baby Dragon', false],
        ])
        ->and(collect($groups['builder_troops']->units)->last()->name)->toBe('Brand New Builder Troop')
        ->and(collect($groups['builder_troops']->units)->firstWhere('name', 'Power P.E.K.K.A')?->locked)->toBeTrue();
});

it('marks maxed units and keeps a missing max level unknown', function () {
    withoutCatalogue();
    $units = app(ProgressionGrid::class)->groups([], [], [unitRow('Barbarian', 12, 12), unitRow('Archer', 9, 12), unitRow('Giant', 4, null)], [])[0]->units;

    expect(collect($units)->mapWithKeys(fn ($u) => [$u->name => [$u->level, $u->maxLevel, $u->maxed]])->all())
        ->toBe(['Archer' => [9, 12, false], 'Barbarian' => [12, 12, true], 'Giant' => [4, null, false]]);
});

it('resolves a unit the pack does not have to its named placeholder (specs/23 §5)', function () {
    withoutCatalogue();
    $unit = app(ProgressionGrid::class)->groups([], [], [unitRow('Brand New Troop')], [])[0]->units[0];

    expect($unit->asset->url)->toBeNull()
        ->and($unit->asset->alt)->toBe('Brand New Troop')
        ->and($unit->asset->short)->not->toBe('');
});
