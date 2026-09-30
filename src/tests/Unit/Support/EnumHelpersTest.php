<?php

use Tests\Support\Fixtures\SampleStatus;

it('lists values in declaration order', function () {
    expect(SampleStatus::values())->toBe(['active', 'suspended']);
});

it('builds select options with label and colour token', function () {
    expect(SampleStatus::options())->toBe([
        ['value' => 'active', 'label' => 'Active', 'color' => 'state-success'],
        ['value' => 'suspended', 'label' => 'Suspended', 'color' => 'state-danger'],
    ]);
});
