<?php

use App\Models\User;
use Database\Seeders\DatabaseSeeder;

// The local staff accounts share a known password, so they are never seeded on a shared host.

it('seeds one account per staff role locally', function () {
    $this->seed(DatabaseSeeder::class);

    expect(User::query()->pluck('username')->all())->toContain('test_moderator', 'test_admin', 'test_super_admin');
});

it('seeds no staff accounts outside local', function () {
    app()->detectEnvironment(fn () => 'staging');

    $this->seed(DatabaseSeeder::class);

    expect(User::query()->where('role', '!=', 'user')->count())->toBe(0);
});
