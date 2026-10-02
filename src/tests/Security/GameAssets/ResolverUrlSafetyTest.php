<?php

use App\Domain\GameAssets\Services\GameAssetPolicy;
use App\Domain\GameAssets\Services\GameAssetResolver;
use App\Domain\GameAssets\Support\PackManifest;
use Tests\Support\GameAssets\InteractsWithPacks;

// API-supplied URLs (clan badges, league icons) cross a trust boundary: only https URLs on the
// allowlisted asset host may reach an <img src>.

beforeEach(fn () => config(['assets.enabled' => true, 'assets.pack_version' => null]));

it('refuses remote URLs that are not https on an allowlisted host', function (string $url) {
    $resolver = app(GameAssetResolver::class);

    expect($resolver->clanBadge(['medium' => $url], 'Clan')->url)->toBeNull()
        ->and($resolver->league(1, 'League', $url)->url)->toBeNull();
})->with([
    'javascript' => 'javascript:alert(1)',
    'data' => 'data:image/svg+xml,<svg onload=alert(1)>',
    'http' => 'http://api-assets.clashofclans.com/badges/200/a.png',
    'other host' => 'https://evil.test/a.png',
    'lookalike host' => 'https://api-assets.clashofclans.com.evil.test/a.png',
    'userinfo trick' => 'https://api-assets.clashofclans.com@evil.test/a.png',
    'explicit port' => 'https://api-assets.clashofclans.com:8443/a.png',
    'protocol relative' => '//api-assets.clashofclans.com/a.png',
    'not a url' => 'badge.png',
]);

it('accepts the allowlisted host over https', function () {
    expect(GameAssetResolver::isAllowedRemote('https://API-ASSETS.clashofclans.com/badges/200/a.png'))->toBeTrue();
});

describe('pack URLs', function () {
    uses(InteractsWithPacks::class);

    beforeEach(function () {
        $this->setUpPack();
        $this->artisan('assets:publish-pack', ['path' => $this->packDir, '--pack-version' => '1'])->assertSuccessful();
    });
    afterEach(fn () => $this->tearDownPack());

    it('only ever points into game/{version}/ on the CDN', function () {
        config(['assets.pack_version' => '1']);
        app()->forgetScopedInstances();
        $resolver = app(GameAssetResolver::class);

        foreach ([$resolver->unit('Barbarian'), $resolver->unit('Archer Queen'), $resolver->townHall(16), $resolver->league(29000022, 'Legend League')] as $asset) {
            expect($asset->url)->toStartWith('https://cdn.test/game/1/');
        }
    });

    it('resolves nothing for a hostile pack version', function (string $version) {
        config(['assets.pack_version' => $version]);
        app()->forgetScopedInstances();
        $resolver = app(GameAssetResolver::class);

        expect($resolver->unit('Barbarian')->url)->toBeNull()
            ->and($resolver->townHall(16)->url)->toBeNull()
            ->and($resolver->league(29000022, 'Legend League')->url)->toBeNull();
    })->with(['../1', '1/../../public', 'a b', '1/', '.hidden', "1\n", str_repeat('9', 40)]);
});

it('keeps every pack 1 asset inside game/1/ on the CDN', function () {
    config(['assets.pack_version' => '1', 'assets.cdn_url' => 'https://cdn.test']);
    app()->forgetScopedInstances();
    $resolver = app(GameAssetResolver::class);

    foreach (PackManifest::fromFile(resource_path('game-assets/1/manifest.json'))->entries() as $key => $entry) {
        $asset = $entry->category->isUnit()
            ? $resolver->unit($entry->ref, $entry->village)
            : $resolver->townHall((int) $entry->ref, $entry->village);

        expect($asset->url)->toBe("https://cdn.test/game/1/{$key}")
            ->and($asset->url)->not->toContain('..');
    }
});

it('rejects versions with a trailing newline', function () {
    expect(GameAssetPolicy::isValidVersion("1\n"))->toBeFalse()
        ->and(GameAssetPolicy::isValidVersion('1'))->toBeTrue();
});
