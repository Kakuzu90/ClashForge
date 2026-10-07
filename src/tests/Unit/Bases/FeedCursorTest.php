<?php

use App\Domain\Bases\Data\FeedFiltersData;
use App\Domain\Bases\Enums\BaseCategory;
use App\Domain\Bases\Enums\FeedSort;
use App\Domain\Bases\Support\FeedCursor;
use Illuminate\Support\Facades\Crypt;
use Tests\TestCase;

// P3-03: the encrypted keyset cursor (specs/17 §4).

uses(TestCase::class);

it('round-trips a position for the feed it was made for', function () {
    $filters = new FeedFiltersData(15, 17, BaseCategory::War, sort: FeedSort::Trending);
    $cursor = FeedCursor::decode(FeedCursor::for($filters, '0.33333334', 42, 3), $filters);

    // `value` is Pest's own property on an expectation, so compare the fields as a list.
    expect([$cursor?->value, $cursor?->id, $cursor?->page])->toBe(['0.33333334', 42, 3]);
});

it('refuses a cursor made for other filters', function (FeedFiltersData $other) {
    $cursor = FeedCursor::for(new FeedFiltersData(sort: FeedSort::Newest), '2026-10-07 12:00:00', 7, 2);

    expect(FeedCursor::decode($cursor, $other))->toBeNull();
})->with([
    'sort' => [new FeedFiltersData(sort: FeedSort::Trending)],
    'TH' => [new FeedFiltersData(16, 16, sort: FeedSort::Newest)],
    'tag' => [new FeedFiltersData(tag: 'box', sort: FeedSort::Newest)],
    'video' => [new FeedFiltersData(hasVideo: true, sort: FeedSort::Newest)],
]);

it('refuses a cursor made with another key', function () {
    $filters = new FeedFiltersData;
    $cursor = FeedCursor::for($filters, '1', 7, 2);
    config(['app.key' => 'base64:'.base64_encode(random_bytes(32))]);
    app()->forgetInstance('encrypter');
    Crypt::clearResolvedInstance('encrypter');

    expect(FeedCursor::decode($cursor, $filters))->toBeNull();
});

it('refuses tampered or malformed cursors', function (string $cursor) {
    expect(FeedCursor::decode($cursor, new FeedFiltersData))->toBeNull();
})->with(['', '.', 'abc', 'abc.def', str_repeat('A', 300)]);

it('names each feed by every filter, and caches only the viewer-independent orders', function () {
    expect((new FeedFiltersData)->signature())->not->toBe((new FeedFiltersData(minLikes: 5))->signature())
        ->and((new FeedFiltersData(16, 16))->signature())->not->toBe((new FeedFiltersData(16, 17))->signature())
        ->and((new FeedFiltersData(16, 16, BaseCategory::War))->cacheable())->toBeTrue()
        ->and((new FeedFiltersData(sort: FeedSort::Newest))->cacheable())->toBeTrue()
        ->and((new FeedFiltersData(sort: FeedSort::MostLiked))->cacheable())->toBeFalse()
        ->and((new FeedFiltersData(tag: 'box'))->cacheable())->toBeFalse()
        ->and((new FeedFiltersData(hasVideo: true))->cacheable())->toBeFalse();
});
