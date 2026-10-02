<?php

use App\Domain\GameAssets\Services\GameAssetCatalogue;

// API names → catalogue slugs (P2-04 Q3), for ordering units with or without a pack image.

it('maps API names to catalogue slugs', function (string $name, string $slug) {
    expect(app(GameAssetCatalogue::class)->slug($name))->toBe($slug);
})->with([
    ['Barbarian King', 'barbarian-king'],
    ['P.E.K.K.A', 'pekka'],
    ['L.A.S.S.I', 'lassi'],
    ['Healing Spell', 'healing'],
    ['Ice Block Spell', 'ice-block'],
    ['Wall Wrecker', 'wall-wrecker'],
    ["Dragon's Breath", 'dragons-breath'],
    ['  Electro  Titan ', 'electro-titan'],
]);

it('takes an alias over the rule', function () {
    config(['assets.aliases' => ['Angry Jelly Spell' => 'angry']]);

    expect(app(GameAssetCatalogue::class)->slug('Angry Jelly Spell'))->toBe('angry');
});

it('reads nested catalogue lists in order', function () {
    $positions = app(GameAssetCatalogue::class)->positions('units');

    expect($positions['barbarian'])->toBe(0)
        ->and($positions['minion'])->toBeGreaterThan($positions['meteor-golem']);
});

it('maps every pack 1 unit name to its file slug', function () {
    $manifest = json_decode((string) file_get_contents(resource_path('game-assets/1/manifest.json')), true);
    $catalogue = app(GameAssetCatalogue::class);

    foreach ($manifest['assets'] as $entry) {
        if (in_array($entry['category'], ['town_hall', 'league'], true)) {
            continue;
        }
        expect($catalogue->slug($entry['ref']))->toBe(pathinfo($entry['key'], PATHINFO_FILENAME));
    }
});
