<?php

use Tests\Support\Fixtures\OtherCode;
use Tests\Support\Fixtures\SampleCode;

it('normalises and keeps a valid value', function () {
    expect(SampleCode::from('  ab12 ')->value)->toBe('AB12');
});

it('rejects an invalid value at construction', function () {
    SampleCode::from('a!');
})->throws(InvalidArgumentException::class, 'Must be 3 to 8 letters or digits.');

it('returns null from tryFrom for invalid or null input', function () {
    expect(SampleCode::tryFrom('no way!'))->toBeNull()
        ->and(SampleCode::tryFrom(null))->toBeNull()
        ->and(SampleCode::tryFrom('abc'))->toEqual(SampleCode::from('ABC'));
});

it('compares by value and type', function () {
    expect(SampleCode::from('abc')->equals(SampleCode::from('ABC')))->toBeTrue()
        ->and(SampleCode::from('abc')->equals(SampleCode::from('abd')))->toBeFalse()
        ->and(SampleCode::from('ABC')->equals(OtherCode::from('ABC')))->toBeFalse();
});

it('serialises to its string value', function () {
    $code = SampleCode::from('abc');

    expect((string) $code)->toBe('ABC')
        ->and(json_encode(['code' => $code]))->toBe('{"code":"ABC"}');
});

it('reports the validation error without constructing', function () {
    expect(SampleCode::errorFor('abc'))->toBeNull()
        ->and(SampleCode::errorFor('x'))->toBe('Must be 3 to 8 letters or digits.');
});
