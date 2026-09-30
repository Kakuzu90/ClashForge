<?php

use App\Models\User;
use Illuminate\Support\Facades\Gate;

// specs/04 §2 rule 1: staff act only on accounts they strictly outrank.

function actorCan(User $actor, string $ability, User $target): bool
{
    return Gate::forUser($actor)->allows($ability, $target);
}

it('lets a moderator warn and restrict a user but not suspend or ban', function () {
    $moderator = User::factory()->moderator()->create();
    $user = User::factory()->create();

    expect(actorCan($moderator, 'warn', $user))->toBeTrue()
        ->and(actorCan($moderator, 'restrict', $user))->toBeTrue()
        ->and(actorCan($moderator, 'suspend', $user))->toBeFalse()
        ->and(actorCan($moderator, 'ban', $user))->toBeFalse();
});

it('stops staff acting on someone at their own level or above', function (string $actorState, string $targetState) {
    $actor = User::factory()->{$actorState}()->create();
    $target = User::factory()->{$targetState}()->create();

    foreach (['warn', 'restrict', 'suspend', 'ban', 'liftSanction', 'changeRole', 'hardDelete'] as $ability) {
        expect(actorCan($actor, $ability, $target))->toBeFalse("{$actorState} → {$ability} {$targetState}");
    }
})->with([
    'moderator on moderator' => ['moderator', 'moderator'],
    'moderator on admin' => ['moderator', 'admin'],
    'admin on admin' => ['admin', 'admin'],
    'admin on super admin' => ['admin', 'superAdmin'],
    'super admin on super admin' => ['superAdmin', 'superAdmin'],
]);

it('lets an admin sanction a moderator but not change roles', function () {
    $admin = User::factory()->admin()->create();
    $moderator = User::factory()->moderator()->create();

    expect(actorCan($admin, 'suspend', $moderator))->toBeTrue()
        ->and(actorCan($admin, 'ban', $moderator))->toBeTrue()
        ->and(actorCan($admin, 'changeRole', $moderator))->toBeFalse()
        ->and(actorCan($admin, 'hardDelete', $moderator))->toBeFalse();
});

it('lets a super admin change the role of an admin', function () {
    expect(actorCan(User::factory()->superAdmin()->create(), 'changeRole', User::factory()->admin()->create()))->toBeTrue();
});

it('never lets someone act on their own account', function () {
    $superAdmin = User::factory()->superAdmin()->create();

    expect(actorCan($superAdmin, 'ban', $superAdmin))->toBeFalse();
});

it('gives a plain user no action on anyone', function () {
    $user = User::factory()->create();

    expect(actorCan($user, 'warn', User::factory()->create()))->toBeFalse();
});
