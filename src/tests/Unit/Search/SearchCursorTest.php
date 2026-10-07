<?php

use App\Domain\Search\Data\SearchCursor;
use Carbon\CarbonImmutable;
use Tests\TestCase;

// specs/17 §4: search cursors are opaque, bound to their search, and carry the ranking time.

uses(TestCase::class);

it('round-trips the position, the page and the ranking time', function () {
    $at = CarbonImmutable::parse('2026-10-07 12:00:00');
    $cursor = SearchCursor::decode(SearchCursor::for('v1|bases|ring', ['0.4321', '12'], 42, 3, $at), 'v1|bases|ring');

    expect($cursor?->values)->toBe(['0.4321', '12'])
        ->and($cursor?->id)->toBe(42)
        ->and($cursor?->page)->toBe(3)
        ->and($cursor?->at->getTimestamp())->toBe($at->getTimestamp());
});

it('refuses a cursor from another search, a tampered one and garbage', function () {
    $cursor = SearchCursor::for('v1|bases|ring', ['1'], 42, 2, CarbonImmutable::now());

    expect(SearchCursor::decode($cursor, 'v1|bases|box'))->toBeNull()
        ->and(SearchCursor::decode(substr($cursor, 0, -4).'AAAA', 'v1|bases|ring'))->toBeNull()
        ->and(SearchCursor::decode('not-a-cursor', 'v1|bases|ring'))->toBeNull()
        ->and(SearchCursor::decode(base64_encode('{"v":["1"],"i":42,"p":2}'), 'v1|bases|ring'))->toBeNull();
});
