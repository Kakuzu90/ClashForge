<?php

use App\Domain\Media\Enums\MediaCollection;
use App\Domain\Media\Enums\MediaFailureReason;
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

it('takes replay videos now that their processor ships (P3-02)', function () {
    expect(MediaCollection::uploadable())->toContain(MediaCollection::BaseVideo)
        ->and(MediaCollection::BaseVideo->kind())->toBe(MediaKind::Video)
        ->and(MediaCollection::BaseVideo->visibility())->toBe(MediaVisibility::Public);
});

it('reads the video limits from config', function () {
    config(['media.video.max_duration' => 30, 'media.video.max_output_bytes' => 1024 * 1024]);

    expect(MediaFailureReason::TooLong->message(MediaKind::Video))->toBe('This video is longer than 30 seconds. Trim it and upload it again.')
        ->and(MediaFailureReason::OutputTooLarge->message(MediaKind::Video))->toBe('This video is still over 1 MB after compression. Upload a shorter clip.')
        ->and(MediaFailureReason::ProcessingError->message(MediaKind::Video))->toContain('this video')
        ->and(MediaFailureReason::ProcessingError->label())->toContain('this image');
});

it('takes private dispute evidence with a full and a thumbnail rendition (P2-16)', function () {
    expect(MediaCollection::uploadable())->toContain(MediaCollection::Evidence)
        ->and(MediaCollection::Evidence->visibility())->toBe(MediaVisibility::Private)
        ->and(array_keys(MediaCollection::Evidence->variants()))->toBe(['full', 'thumb']);
});
