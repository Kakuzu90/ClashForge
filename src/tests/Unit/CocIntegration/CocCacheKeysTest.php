<?php

use App\Domain\CocIntegration\Data\ClanTag;
use App\Domain\CocIntegration\Data\PlayerTag;
use App\Domain\CocIntegration\Support\CocCacheKeys;

it('builds the specs/21 §3 keys without the hash', function () {
    $player = PlayerTag::from('#2PQ8GRJC');
    $clan = ClanTag::from('#2Q8URJ9L');

    expect(CocCacheKeys::player($player))->toBe('coc:player:2PQ8GRJC')
        ->and(CocCacheKeys::playerLast($player))->toBe('coc:player:2PQ8GRJC:last')
        ->and(CocCacheKeys::clan($clan))->toBe('coc:clan:2Q8URJ9L')
        ->and(CocCacheKeys::clanLast($clan))->toBe('coc:clan:2Q8URJ9L:last')
        ->and(CocCacheKeys::circuit())->toBe('coc:circuit');
});

it('keeps player and clan misses apart', function () {
    expect(CocCacheKeys::notFound('player', PlayerTag::from('#2PQ')))->toBe('coc:404:player:2PQ')
        ->and(CocCacheKeys::notFound('clan', ClanTag::from('#2PQ')))->toBe('coc:404:clan:2PQ');
});

it('names the rate buckets', function () {
    expect(CocCacheKeys::rate('global', 'second'))->toBe('coc-rate:global:second')
        ->and(CocCacheKeys::rate('background', 'minute'))->toBe('coc-rate:background:minute')
        ->and(CocCacheKeys::rateKey('abcd1234'))->toBe('coc-rate:key:abcd1234');
});
