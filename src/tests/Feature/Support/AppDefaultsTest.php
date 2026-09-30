<?php

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\LazyLoadingViolationException;
use Illuminate\Support\Facades\Date;

it('uses immutable dates', function () {
    expect(Date::now())->toBeInstanceOf(CarbonImmutable::class)
        ->and(now())->toBeInstanceOf(CarbonImmutable::class);
});

it('throws on lazy loading outside production', function () {
    User::factory()->count(2)->create();

    $users = User::all();
    $users->first()->notifications;
})->throws(LazyLoadingViolationException::class);
