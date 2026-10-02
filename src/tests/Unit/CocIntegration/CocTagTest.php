<?php

use App\Domain\CocIntegration\Data\ClanTag;
use App\Domain\CocIntegration\Data\CocTag;
use App\Domain\CocIntegration\Data\PlayerTag;

it('normalises a tag to its canonical form (FR-COC-2)', function (string $input, string $expected) {
    expect(PlayerTag::from($input)->value)->toBe($expected)
        ->and(ClanTag::from($input)->value)->toBe($expected);
})->with([
    'canonical' => ['#2PQ8GRJC', '#2PQ8GRJC'],
    'lowercase' => ['#2pq8grjc', '#2PQ8GRJC'],
    'missing hash' => ['2PQ8GRJC', '#2PQ8GRJC'],
    'surrounding whitespace' => ["  #2pq8grjc \n", '#2PQ8GRJC'],
    'letter O read as zero' => ['#2PQ8GRJO', '#2PQ8GRJ0'],
    'lowercase o read as zero' => ['oPQ', '#0PQ'],
    'shortest' => ['#PYL', '#PYL'],
    'longest' => ['#'.str_repeat('2', CocTag::MAX), '#'.str_repeat('2', CocTag::MAX)],
]);

it('refuses what is not a tag', function (string $input) {
    expect(PlayerTag::tryFrom($input))->toBeNull()
        ->and(PlayerTag::errorFor($input))->toBeString();
})->with([
    'empty' => [''],
    'hash only' => ['#'],
    'too short' => ['#'.str_repeat('2', CocTag::MIN - 1)],
    'too long' => ['#'.str_repeat('2', CocTag::MAX + 1)],
    'letters outside the alphabet' => ['#ABCD'],
    'space inside' => ['#2PQ 8GRJ'],
    'two hashes' => ['##2PQ8GRJ'],
    'Cyrillic look-alike' => ['#2PQ8GRJС'],
    'full-width digit' => ['#2PQ８GRJ'],
    'NUL byte' => ["#2PQ\0GRJ"],
]);

it('encodes the hash for the URL path and drops it for file names', function () {
    $tag = PlayerTag::from('#2PQ8GRJC');

    expect($tag->urlEncoded())->toBe('%232PQ8GRJC')
        ->and($tag->bare())->toBe('2PQ8GRJC')
        ->and((string) $tag)->toBe('#2PQ8GRJC')
        ->and(json_encode($tag))->toBe('"#2PQ8GRJC"');
});

it('names the kind of tag in its messages', function () {
    expect(PlayerTag::errorFor(''))->toBe('Enter a player tag.')
        ->and(ClanTag::errorFor('#ABCD'))->toContain('A clan tag uses only')
        ->and(PlayerTag::errorFor('#22'))->toBe('A player tag has '.CocTag::MIN.' to '.CocTag::MAX.' characters after the #.');
});

it('keeps player and clan tags apart', function () {
    expect(PlayerTag::from('#2PQ')->equals(ClanTag::from('#2PQ')))->toBeFalse()
        ->and(PlayerTag::from('#2pq')->equals(PlayerTag::from('2PQ')))->toBeTrue();
});
