<?php

use App\Support\Privacy\IpHash;

it('hashes an IP with the configured key and never returns it raw', function () {
    config(['platform.ip_hash_salt' => 'key-one']);
    $first = IpHash::of('203.0.113.9');

    expect($first)->toHaveLength(64)
        ->and($first)->not->toContain('203.0.113.9')
        ->and(IpHash::of('203.0.113.9'))->toBe($first);

    config(['platform.ip_hash_salt' => 'key-two']);
    expect(IpHash::of('203.0.113.9'))->not->toBe($first);
});

it('returns null when there is no IP', function () {
    expect(IpHash::of(null))->toBeNull()->and(IpHash::of(''))->toBeNull();
});
