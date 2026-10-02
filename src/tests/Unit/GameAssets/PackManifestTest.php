<?php

use App\Domain\GameAssets\Enums\Village;
use App\Domain\GameAssets\Exceptions\InvalidManifest;
use App\Domain\GameAssets\Support\PackManifest;

function manifestAsset(array $overrides = []): array
{
    return array_merge([
        'key' => 'units/barbarian.png',
        'category' => 'troop',
        'ref' => 'Barbarian',
        'display_name' => 'Barbarian',
        'village' => 'home',
        'source' => 'fixture',
        'sha256' => str_repeat('a', 64),
        'bytes' => 100,
        'width' => 64,
        'height' => 64,
    ], $overrides);
}

function manifestJson(array $assets, string $version = '1'): string
{
    return json_encode(['version' => $version, 'assets' => $assets]);
}

it('parses a valid manifest and finds entries case-insensitively', function () {
    $manifest = PackManifest::fromJson(manifestJson([manifestAsset(), manifestAsset(['key' => 'townhalls/16.png', 'category' => 'town_hall', 'ref' => '16', 'display_name' => 'Town Hall 16', 'village' => 'home'])]));

    expect($manifest->version)->toBe('1')
        ->and($manifest->find('unit', 'BARBARIAN', Village::Home)?->key)->toBe('units/barbarian.png')
        ->and($manifest->find('town_hall', '16', Village::Home)?->key)->toBe('townhalls/16.png')
        ->and($manifest->find('town_hall', '16', Village::Builder))->toBeNull()
        ->and(PackManifest::fromJson($manifest->toJson())->toJson())->toBe($manifest->toJson());
});

it('rejects invalid manifests with every problem listed', function (string $json, string $problem) {
    try {
        PackManifest::fromJson($json);
        $this->fail('expected InvalidManifest');
    } catch (InvalidManifest $e) {
        expect(implode("\n", $e->problems))->toContain($problem);
    }
})->with([
    'not json' => ['{', 'not valid JSON'],
    'wrong shape' => ['{"assets": []}', 'expected {"version"'],
    'path traversal' => [manifestJson([manifestAsset(['key' => 'units/../../etc.png'])]), 'key must look like'],
    'absolute key' => [manifestJson([manifestAsset(['key' => '/units/a.png'])]), 'key must look like'],
    'upper case key' => [manifestJson([manifestAsset(['key' => 'units/Barbarian.png'])]), 'key must look like'],
    'unknown category' => [manifestJson([manifestAsset(['category' => 'building'])]), 'category must be one of'],
    'wrong folder' => [manifestJson([manifestAsset(['category' => 'league'])]), 'belongs in leagues/'],
    'unit without village' => [manifestJson([manifestAsset(['village' => null])]), 'troop entries need a village'],
    'village disagrees with the folder' => [manifestJson([manifestAsset(['village' => 'builderBase'])]), 'does not match the folder'],
    'builder base asset marked home' => [manifestJson([manifestAsset(['key' => 'townhalls/builder-base/5.png', 'category' => 'town_hall', 'ref' => '5'])]), 'does not match the folder'],
    'league in a builder-base folder' => [manifestJson([manifestAsset(['key' => 'leagues/builder-base/1.png', 'category' => 'league', 'ref' => '1', 'village' => null])]), 'leagues are keyed by id'],
    'nested folder' => [manifestJson([manifestAsset(['key' => 'units/extra/barbarian.png'])]), 'key must look like'],
    'bad checksum' => [manifestJson([manifestAsset(['sha256' => 'abc'])]), 'sha256 must be'],
    'missing source' => [manifestJson([manifestAsset(['source' => ''])]), 'source is required'],
    'zero width' => [manifestJson([manifestAsset(['width' => 0])]), 'width must be a positive integer'],
    'trailing newline in key' => [manifestJson([manifestAsset(['key' => "units/barbarian.png\n"])]), 'key must look like'],
    'duplicate key' => [manifestJson([manifestAsset(), manifestAsset()]), 'listed twice'],
    'duplicate name' => [manifestJson([manifestAsset(), manifestAsset(['key' => 'units/barbarian-2.png'])]), 'same troop and name'],
]);
