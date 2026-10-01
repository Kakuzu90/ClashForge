<?php

use App\Support\Health\FailedJobsSummary;

// The dashboard's job class label from a failed job's payload `displayName`.

it('keeps a class name as it is', function () {
    expect(FailedJobsSummary::name('App\\Domain\\Media\\Jobs\\ProcessMediaJob'))->toBe('App\\Domain\\Media\\Jobs\\ProcessMediaJob');
});

it('reads a missing, blank or non-string name as unreadable', function (mixed $raw) {
    expect(FailedJobsSummary::name($raw))->toBeNull();
})->with([null, '', '   ', 42, false]);

it('caps a very long name', function () {
    expect(mb_strlen((string) FailedJobsSummary::name(str_repeat('a', 500))))->toBeLessThanOrEqual(203);
});
