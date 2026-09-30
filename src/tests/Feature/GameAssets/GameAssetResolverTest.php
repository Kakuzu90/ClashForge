<?php

use App\Domain\GameAssets\Enums\GameAssetKind;
use App\Domain\GameAssets\Enums\Village;
use App\Domain\GameAssets\Services\GameAssetResolver;
use Illuminate\Support\Facades\Log;
use Tests\Support\GameAssets\InteractsWithPacks;

uses(InteractsWithPacks::class);

beforeEach(function () {
    $this->setUpPack();
    $this->artisan('assets:publish-pack', ['path' => $this->packDir, '--pack-version' => '1'])->assertSuccessful();
    config(['assets.pack_version' => '1']);
});
afterEach(fn () => $this->tearDownPack());

function resolver(): GameAssetResolver
{
    app()->forgetScopedInstances();

    return app(GameAssetResolver::class);
}

it('resolves catalogue assets to the active pack on the CDN, with intrinsic size', function () {
    $unit = resolver()->unit('archer queen');

    expect($unit->kind)->toBe(GameAssetKind::Unit)
        ->and($unit->url)->toBe('https://cdn.test/game/1/units/archer-queen.png')
        ->and($unit->alt)->toBe('Archer Queen')
        ->and($unit->short)->toBe('AQ')
        ->and([$unit->width, $unit->height])->toBe([65, 65])
        ->and(resolver()->townHall(16)->url)->toBe('https://cdn.test/game/1/townhalls/16.png')
        ->and(resolver()->townHall(16)->alt)->toBe('Town Hall 16')
        ->and(resolver()->league(29000022, 'Legend League', 'https://api-assets.clashofclans.com/leagues/x.png')->url)->toBe('https://cdn.test/game/1/leagues/29000022.png');
});

it('keeps unknown units, levels and villages as placeholders with their name', function () {
    $unknown = resolver()->unit('Brand New Troop');

    expect($unknown->url)->toBeNull()
        ->and($unknown->alt)->toBe('Brand New Troop')
        ->and($unknown->short)->toBe('BN')
        ->and(resolver()->unit('Barbarian', Village::Builder)->url)->toBeNull()
        ->and(resolver()->townHall(18)->url)->toBeNull()
        ->and(resolver()->townHall(18)->short)->toBe('18');
});

it('falls back to the API icon for leagues missing from the pack', function () {
    $league = resolver()->league(29000099, 'Titan League I', 'https://api-assets.clashofclans.com/leagues/72/titan.png');

    expect($league->url)->toBe('https://api-assets.clashofclans.com/leagues/72/titan.png')
        ->and($league->alt)->toBe('Titan League I')
        ->and(resolver()->league(null, 'Unranked')->url)->toBeNull();
});

it('passes clan badges through by size', function () {
    $urls = ['small' => 'https://api-assets.clashofclans.com/badges/70/a.png', 'medium' => 'https://api-assets.clashofclans.com/badges/200/a.png'];

    expect(resolver()->clanBadge($urls, 'Night Owls')->url)->toBe($urls['medium'])
        ->and(resolver()->clanBadge($urls, 'Night Owls', 'small')->url)->toBe($urls['small'])
        ->and(resolver()->clanBadge($urls, 'Night Owls', 'large')->url)->toBeNull()
        ->and(resolver()->clanBadge($urls, 'Night Owls', 'huge')->url)->toBeNull()
        ->and(resolver()->clanBadge($urls, 'Night Owls')->alt)->toBe('Night Owls clan badge');
});

it('returns placeholders for everything when the kill switch is off', function () {
    config(['assets.enabled' => false]);
    $r = resolver();

    expect($r->unit('Barbarian')->url)->toBeNull()
        ->and($r->townHall(16)->url)->toBeNull()
        ->and($r->league(29000022, 'Legend League', 'https://api-assets.clashofclans.com/l.png')->url)->toBeNull()
        ->and($r->league(29000099, 'Titan', 'https://api-assets.clashofclans.com/l.png')->url)->toBeNull()
        ->and($r->clanBadge(['medium' => 'https://api-assets.clashofclans.com/b.png'], 'Night Owls')->url)->toBeNull()
        ->and($r->unit('Barbarian')->alt)->toBe('Barbarian');
});

it('returns placeholders when no pack is active', function () {
    config(['assets.pack_version' => null]);

    expect(resolver()->unit('Barbarian')->url)->toBeNull();
});

it('degrades to placeholders when the committed manifest is unreadable', function () {
    Log::spy();
    file_put_contents(str_replace('{version}', '1', config('assets.manifest_path')), '{not json');

    expect(resolver()->unit('Barbarian')->url)->toBeNull();
    Log::shouldHaveReceived('error')->withArgs(fn (string $message) => $message === 'assets.manifest_unreadable');
});

it('ignores a committed manifest for another version', function () {
    config(['assets.pack_version' => '2']);
    @mkdir(dirname(str_replace('{version}', '2', config('assets.manifest_path'))), 0777, true);
    copy(str_replace('{version}', '1', config('assets.manifest_path')), str_replace('{version}', '2', config('assets.manifest_path')));

    expect(resolver()->unit('Barbarian')->url)->toBeNull();
});

it('uses the storage URL when no CDN origin is configured', function () {
    config(['assets.cdn_url' => null]);

    expect(resolver()->unit('Barbarian')->url)->toBe($this->assetsDisk()->url('game/1/units/barbarian.png'));
});

it('reads the prefix, remote hosts and badge sizes from config', function () {
    config([
        'assets.prefix' => 'catalogue',
        'assets.remote_hosts' => ['badges.test'],
        'assets.badge_sizes' => ['large'],
    ]);
    $r = resolver();

    expect($r->townHall(16)->url)->toBe('https://cdn.test/catalogue/1/townhalls/16.png')
        ->and($r->clanBadge(['large' => 'https://badges.test/l.png'], 'Clan', 'large')->url)->toBe('https://badges.test/l.png')
        ->and($r->clanBadge(['medium' => 'https://badges.test/m.png'], 'Clan', 'medium')->url)->toBeNull()
        ->and($r->clanBadge(['large' => 'https://api-assets.clashofclans.com/l.png'], 'Clan', 'large')->url)->toBeNull();
});
