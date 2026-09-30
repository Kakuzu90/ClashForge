<?php

use App\Support\Rules\ValidValueObject;
use Illuminate\Support\Facades\Validator;
use Tests\Support\Fixtures\SampleCode;
use Tests\TestCase;

uses(TestCase::class);

it('passes a valid value', function () {
    $validator = Validator::make(['code' => 'ab12'], ['code' => [new ValidValueObject(SampleCode::class)]]);

    expect($validator->passes())->toBeTrue();
});

it('fails with the value object message', function () {
    $validator = Validator::make(['code' => 'x'], ['code' => [new ValidValueObject(SampleCode::class)]]);

    expect($validator->errors()->first('code'))->toBe('Must be 3 to 8 letters or digits.');
});

it('fails on non-string input', function () {
    $validator = Validator::make(['code' => ['a']], ['code' => [new ValidValueObject(SampleCode::class)]]);

    expect($validator->errors()->first('code'))->toBe('The code must be a string.');
});
