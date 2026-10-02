<?php

use App\Domain\CocIntegration\Support\CircuitBreaker;

it('opens on the error rate only above the line and with enough samples', function (int $ok, int $fail, bool $expected) {
    expect(CircuitBreaker::exceeds($ok, $fail, 20, 0.5))->toBe($expected);
})->with([
    'no calls' => [0, 0, false],
    'too few samples, all failed' => [0, 19, false],
    'exactly half' => [10, 10, false],
    'just above half' => [9, 11, true],
    'all failed' => [0, 20, true],
    'mostly fine' => [18, 2, false],
]);
