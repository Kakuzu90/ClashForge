<?php

use App\Domain\Media\Enums\MediaCollection;
use App\Domain\Media\Enums\MediaKind;
use App\Domain\Media\Enums\MediaVisibility;
use App\Domain\Media\Enums\VariantName;

it('reads collection limits from config', function () {
    config(['media.collections.avatar.max_bytes' => 1234, 'media.collections.avatar.accepts_uploads' => false]);

    expect(MediaCollection::Avatar->maxBytes())->toBe(1234)
        ->and(MediaCollection::uploadable())->not->toContain(MediaCollection::Avatar);
});

it('configures every collection with a known kind, visibility and variant names', function () {
    foreach (MediaCollection::cases() as $collection) {
        expect($collection->kind())->toBeInstanceOf(MediaKind::class)
            ->and($collection->visibility())->toBeInstanceOf(MediaVisibility::class)
            ->and($collection->maxBytes())->toBeGreaterThan(0);

        foreach (array_keys($collection->variants()) as $name) {
            expect(VariantName::tryFrom($name))->not->toBeNull();
        }
    }
});

it('holds video and evidence back until their processors ship', function () {
    expect(MediaCollection::uploadable())->not->toContain(MediaCollection::BaseVideo, MediaCollection::Evidence);
});
