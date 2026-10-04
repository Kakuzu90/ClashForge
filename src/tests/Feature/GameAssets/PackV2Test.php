<?php

use App\Domain\GameAssets\Enums\GameAssetCategory;
use App\Domain\GameAssets\Enums\Village;
use App\Domain\GameAssets\Services\GameAssetResolver;
use App\Domain\GameAssets\Support\PackManifest;

// Pack 2: pack 1 plus the files it left out over the old 1 MB limit, and the league emblems.

beforeEach(function () {
    config(['assets.enabled' => true, 'assets.pack_version' => '2', 'assets.cdn_url' => 'https://cdn.test']);
    app()->forgetScopedInstances();
});

function packV2(): PackManifest
{
    return PackManifest::fromFile(resource_path('game-assets/2/manifest.json'));
}

it('parses, and every entry stays within the size limit and names its source', function () {
    $manifest = packV2();

    expect($manifest->version)->toBe('2');

    foreach ($manifest->entries() as $key => $entry) {
        expect($entry->bytes)->toBeLessThanOrEqual((int) config('assets.max_bytes'), $key)
            ->and($entry->source)->toBe('Supercell Fan Kit', $key);
    }
});

it('keeps every pack 1 entry under the same ref', function () {
    $v2 = [];
    foreach (packV2()->entries() as $entry) {
        $v2[$entry->lookupKey()] = true;
    }

    foreach (PackManifest::fromFile(resource_path('game-assets/1/manifest.json'))->entries() as $key => $entry) {
        expect($v2)->toHaveKey($entry->lookupKey(), $key);
    }
});

it('resolves the units pack 1 left out', function (string $name, Village $village, string $key) {
    expect(app(GameAssetResolver::class)->unit($name, $village)->url)->toBe("https://cdn.test/game/2/{$key}");
})->with([
    'Metal Pants' => ['Metal Pants', Village::Home, 'equipments/metal-pants.png'],
    'Archer Puppet' => ['Archer Puppet', Village::Home, 'equipments/archer-puppet.png'],
    'Overgrowth Spell' => ['Overgrowth Spell', Village::Home, 'spells/overgrowth.png'],
    'Druid' => ['Druid', Village::Home, 'units/druid.png'],
    'Battle Machine' => ['Battle Machine', Village::Builder, 'heroes/builder-base/battle-machine.png'],
]);

it('resolves Town Halls 12 to 18', function () {
    foreach (range(12, 18) as $level) {
        expect(app(GameAssetResolver::class)->townHall($level)->url)->toBe("https://cdn.test/game/2/townhalls/{$level}.png");
    }
});

it('resolves every tier of a league to its family emblem, keeping the API name', function (string $name, string $file) {
    $league = app(GameAssetResolver::class)->league(29000099, $name, 'https://api-assets.clashofclans.com/leagues/72/x.png');

    expect($league->url)->toBe("https://cdn.test/game/2/leagues/{$file}.png")
        ->and($league->alt)->toBe($name);
})->with([
    ['Titan League I', 'titan'],
    ['P.E.K.K.A League 20', 'pekka'],
    ['Legend League', 'legend'],
]);

it('lists every packed Home Village unit in the catalogue order config', function () {
    $lists = [
        GameAssetCategory::Troop->value => [...config('assets.units.elixir'), ...config('assets.units.dark-elixir')],
        GameAssetCategory::Spell->value => [...config('assets.spells.elixir'), ...config('assets.spells.dark-elixir')],
        GameAssetCategory::Hero->value => config('assets.heroes'),
        GameAssetCategory::Equipment->value => array_merge(...array_values(config('assets.heroes_equipments'))),
        GameAssetCategory::Pet->value => config('assets.pets'),
        GameAssetCategory::SiegeMachine->value => config('assets.siege-machines'),
    ];

    foreach (packV2()->entries() as $key => $entry) {
        if ($entry->category->isUnit() && $entry->village === Village::Home) {
            expect($lists[$entry->category->value])->toContain(pathinfo($key, PATHINFO_FILENAME));
        }
    }
});

it('names a Home Village unit by its pack file, for units an account has not unlocked', function () {
    $resolver = app(GameAssetResolver::class);

    expect($resolver->unitName('lassi'))->toBe('L.A.S.S.I')
        ->and($resolver->unitName('healing'))->toBe('Healing Spell')
        ->and($resolver->unitName('metal-pants'))->toBe('Metal Pants')
        ->and($resolver->unitName('battle-machine'))->toBeNull()
        ->and($resolver->unitName('not-in-the-pack'))->toBeNull();
});
