<?php

use App\Domain\GameAssets\Enums\GameAssetCategory;
use App\Domain\GameAssets\Enums\Village;
use App\Domain\GameAssets\Services\GameAssetResolver;
use App\Domain\GameAssets\Support\PackManifest;

// The committed manifest of pack 1 (P2-05), read the way the resolver reads it in production.

beforeEach(function () {
    config(['assets.enabled' => true, 'assets.pack_version' => '1', 'assets.cdn_url' => 'https://cdn.test']);
    app()->forgetScopedInstances();
});

function packV1(): PackManifest
{
    return PackManifest::fromFile(resource_path('game-assets/1/manifest.json'));
}

it('parses, and every entry stays within the size limit and names its source', function () {
    $manifest = packV1();

    expect($manifest->version)->toBe('1')
        ->and($manifest->entries())->not->toBeEmpty();

    foreach ($manifest->entries() as $key => $entry) {
        expect($entry->bytes)->toBeLessThanOrEqual((int) config('assets.max_bytes'), $key)
            ->and($entry->source)->toBe('Supercell Fan Kit', $key)
            ->and(pathinfo($key, PATHINFO_EXTENSION))->toBeIn(array_values(config('assets.mimes')), $key);
    }
});

it('ships no league emblems, so leagues use the API icon', function () {
    $leagues = array_filter(packV1()->entries(), fn ($entry) => $entry->category === GameAssetCategory::League);
    $icon = 'https://api-assets.clashofclans.com/leagues/72/legend.png';

    expect($leagues)->toBe([])
        ->and(app(GameAssetResolver::class)->league(29000022, 'Legend League', $icon)->url)->toBe($icon);
});

it('resolves the API names that differ from the file names', function (string $name, Village $village, string $key) {
    $asset = app(GameAssetResolver::class)->unit($name, $village);

    expect($asset->url)->toBe("https://cdn.test/game/1/{$key}")
        ->and($asset->alt)->toBe($name);
})->with([
    'P.E.K.K.A' => ['P.E.K.K.A', Village::Home, 'units/pekka.png'],
    'Power P.E.K.K.A' => ['Power P.E.K.K.A', Village::Builder, 'units/builder-base/power-pekka.png'],
    'L.A.S.S.I' => ['L.A.S.S.I', Village::Home, 'pets/lassi.png'],
    'Healing Spell' => ['Healing Spell', Village::Home, 'spells/healing.png'],
    'Builder Base Baby Dragon' => ['Baby Dragon', Village::Builder, 'units/builder-base/baby-dragon.png'],
    'WebP siege machine' => ['Battle Blimp', Village::Home, 'machines/battle-blimp.webp'],
]);

it('resolves Builder Hall levels separately from Town Hall levels', function () {
    $resolver = app(GameAssetResolver::class);

    expect($resolver->townHall(5, Village::Builder)->url)->toBe('https://cdn.test/game/1/townhalls/builder-base/5.png')
        ->and($resolver->townHall(5, Village::Builder)->alt)->toBe('Builder Hall 5')
        ->and($resolver->townHall(5)->url)->toBe('https://cdn.test/game/1/townhalls/5.png')
        ->and($resolver->townHall(11, Village::Builder)->url)->toBeNull()
        ->and($resolver->townHall(11, Village::Builder)->short)->toBe('11');
});

it('renders units from a future game update as placeholders with their name', function () {
    $player = json_decode((string) file_get_contents(base_path('tests/Fixtures/coc/players/YC8V2QG9.json')), true);
    $resolver = app(GameAssetResolver::class);
    $resolved = [];

    foreach ([...$player['troops'], ...$player['heroes']] as $unit) {
        $asset = $resolver->unit($unit['name'], Village::from($unit['village']));
        $resolved[$unit['name']] = [$asset->url, $asset->alt];
    }

    expect($resolved)->toBe([
        'Barbarian' => ['https://cdn.test/game/1/units/barbarian.png', 'Barbarian'],
        'Troop From A Future Update' => [null, 'Troop From A Future Update'],
        'Hero From A Future Update' => [null, 'Hero From A Future Update'],
    ])->and($resolver->townHall($player['townHallLevel'])->url)->toBeNull();
});

it('still shows only placeholders when the kill switch is off', function () {
    config(['assets.enabled' => false]);
    app()->forgetScopedInstances();
    $resolver = app(GameAssetResolver::class);

    expect($resolver->unit('P.E.K.K.A')->url)->toBeNull()
        ->and($resolver->townHall(5, Village::Builder)->url)->toBeNull();
});

it('lists every packed Home Village unit in the catalogue order config', function () {
    $lists = [
        GameAssetCategory::Troop->value => [...config('assets.units.elixir'), ...config('assets.units.dark-elixir')],
        GameAssetCategory::Spell->value => [...config('assets.spells.elixir'), ...config('assets.spells.dark-elixir')],
        GameAssetCategory::Hero->value => config('assets.heroes'),
        GameAssetCategory::Equipment->value => array_merge(...array_values(config('assets.heroes_equipments'))),
        GameAssetCategory::Pet->value => config('assets.pets'),
        GameAssetCategory::SiegeMachine->value => config('assets.siege-machines'),
    ];

    foreach (packV1()->entries() as $key => $entry) {
        if ($entry->category->isUnit() && $entry->village === Village::Home) {
            expect($lists[$entry->category->value])->toContain(pathinfo($key, PATHINFO_FILENAME));
        }
    }

    expect(array_keys(config('assets.heroes_equipments')))->toBe(config('assets.heroes'));
});
