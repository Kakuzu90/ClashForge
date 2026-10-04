<?php

use App\Domain\GameAssets\Enums\Village;
use App\Domain\GameAssets\Services\GameAssetResolver;
use App\Domain\GameAssets\Support\PackManifest;

// Pack 3: pack 2 with smaller originals for some files, plus the Builder Base league emblems.

beforeEach(function () {
    config(['assets.enabled' => true, 'assets.pack_version' => '3', 'assets.cdn_url' => 'https://cdn.test']);
    app()->forgetScopedInstances();
});

function packV3(): PackManifest
{
    return PackManifest::fromFile(resource_path('game-assets/3/manifest.json'));
}

it('parses, and every entry stays within the size limit and names its source', function () {
    $manifest = packV3();

    expect($manifest->version)->toBe('3');

    foreach ($manifest->entries() as $key => $entry) {
        expect($entry->bytes)->toBeLessThanOrEqual((int) config('assets.max_bytes'), $key)
            ->and($entry->source)->toBe('Supercell Fan Kit', $key);
    }
});

it('keeps every pack 2 entry under the same ref', function () {
    $v3 = [];
    foreach (packV3()->entries() as $entry) {
        $v3[$entry->lookupKey()] = true;
    }

    foreach (PackManifest::fromFile(resource_path('game-assets/2/manifest.json'))->entries() as $key => $entry) {
        expect($v3)->toHaveKey($entry->lookupKey(), $key);
    }
});

it('resolves Builder Base leagues apart from Home Village ones, by family', function () {
    $resolver = app(GameAssetResolver::class);
    $wood = $resolver->league(44000001, 'Wood League V', null, Village::Builder);

    expect($wood->url)->toBe('https://cdn.test/game/3/leagues/builder-base/wood.png')
        ->and($wood->alt)->toBe('Wood League V')
        ->and($resolver->league(44000040, 'Diamond League', null, Village::Builder)->url)->toBe('https://cdn.test/game/3/leagues/builder-base/diamond.png')
        ->and($resolver->league(29000022, 'Legend League')->url)->toBe('https://cdn.test/game/3/leagues/legend.png')
        ->and($resolver->league(29000001, 'Wood League V')->url)->toBeNull()
        ->and($resolver->league(44000001, 'Titan League I', null, Village::Builder)->url)->toBeNull();
});

it('lists every packed Builder Base unit in the Builder Base order config', function () {
    $lists = [...config('assets.bb_heroes'), ...config('assets.bb_units')];

    foreach (packV3()->entries() as $key => $entry) {
        if ($entry->category->isUnit() && $entry->village === Village::Builder) {
            expect($lists)->toContain(pathinfo($key, PATHINFO_FILENAME));
        }
    }
});

it('names a Builder Base unit by its pack file, apart from its Home Village namesake', function () {
    $resolver = app(GameAssetResolver::class);

    expect($resolver->unitName('power-pekka', Village::Builder))->toBe('Power P.E.K.K.A')
        ->and($resolver->unitName('baby-dragon', Village::Builder))->toBe('Baby Dragon')
        ->and($resolver->unitName('power-pekka'))->toBeNull();
});
