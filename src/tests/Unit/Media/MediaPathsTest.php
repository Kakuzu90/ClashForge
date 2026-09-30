<?php

use App\Domain\Media\Support\MediaPaths;
use Carbon\CarbonImmutable;

it('puts every upload key under quarantine/', function () {
    $at = CarbonImmutable::parse('2026-01-05');

    expect(MediaPaths::quarantine('01abc', 'png', $at))->toBe('quarantine/2026/01/01abc/original.png')
        ->toStartWith(MediaPaths::QUARANTINE_PREFIX);
});
