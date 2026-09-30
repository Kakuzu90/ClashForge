<?php

use App\Domain\Media\Enums\MediaVisibility;
use App\Domain\Media\Services\StorageReconciler;
use App\Domain\Media\Support\MediaPaths;

it('scans exactly the media prefixes, and never game/', function () {
    expect(StorageReconciler::PREFIXES)->toBe(['public/', 'quarantine/', 'private/'])
        ->and(StorageReconciler::PREFIXES)->not->toContain('game/', '');
});

it('covers every prefix the pipeline writes to', function () {
    $written = [MediaPaths::QUARANTINE_PREFIX, ...array_map(fn (MediaVisibility $v) => $v->prefix().'/', MediaVisibility::cases())];

    expect(array_diff($written, StorageReconciler::PREFIXES))->toBe([]);
});
