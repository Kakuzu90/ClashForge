<?php

use App\Support\Casts\AsValueObject;
use Illuminate\Database\Eloquent\Model;
use Tests\Support\Fixtures\OtherCode;
use Tests\Support\Fixtures\SampleCode;

beforeEach(function () {
    $this->cast = new AsValueObject(SampleCode::class);
    $this->model = new class extends Model {};
});

it('hydrates the value object from the column', function () {
    expect($this->cast->get($this->model, 'code', 'ABC', []))->toEqual(SampleCode::from('ABC'))
        ->and($this->cast->get($this->model, 'code', null, []))->toBeNull();
});

it('stores the normalised string from a value object or a raw string', function () {
    expect($this->cast->set($this->model, 'code', SampleCode::from('abc'), []))->toBe('ABC')
        ->and($this->cast->set($this->model, 'code', ' abc ', []))->toBe('ABC')
        ->and($this->cast->set($this->model, 'code', null, []))->toBeNull();
});

it('refuses an invalid raw string', function () {
    $this->cast->set($this->model, 'code', 'x', []);
})->throws(InvalidArgumentException::class);

it('refuses a value object of another type', function () {
    $this->cast->set($this->model, 'code', OtherCode::from('ABC'), []);
})->throws(InvalidArgumentException::class);

it('only accepts value object classes', function () {
    new AsValueObject(stdClass::class);
})->throws(InvalidArgumentException::class);
