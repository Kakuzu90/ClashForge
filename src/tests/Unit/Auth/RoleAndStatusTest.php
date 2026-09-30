<?php

use App\Domain\Auth\Enums\Role;
use App\Domain\Auth\Enums\StaffAbility;
use App\Domain\Auth\Enums\UserStatus;

// specs/04 §1: the role hierarchy and what each status allows.

it('orders roles so each includes everything below it', function () {
    expect(Role::SuperAdmin->includes(Role::Admin))->toBeTrue()
        ->and(Role::Admin->includes(Role::Moderator))->toBeTrue()
        ->and(Role::Moderator->includes(Role::Moderator))->toBeTrue()
        ->and(Role::Moderator->includes(Role::Admin))->toBeFalse()
        ->and(Role::User->includes(Role::Moderator))->toBeFalse();
});

it('outranks only a strictly lower role', function () {
    expect(Role::Moderator->outranks(Role::User))->toBeTrue()
        ->and(Role::Moderator->outranks(Role::Moderator))->toBeFalse()
        ->and(Role::Admin->outranks(Role::Admin))->toBeFalse()
        ->and(Role::SuperAdmin->outranks(Role::Admin))->toBeTrue()
        ->and(Role::SuperAdmin->outranks(Role::SuperAdmin))->toBeFalse();
});

it('treats moderator and above as staff', function () {
    expect(array_map(fn (Role $role): bool => $role->isStaff(), Role::cases()))->toBe([false, true, true, true]);
});

it('lets every status but banned sign in', function () {
    expect(array_filter(UserStatus::cases(), fn (UserStatus $s): bool => ! $s->canLogIn()))->toBe([3 => UserStatus::Banned]);
});

it('opens account writes to active and restricted, content writes to active only', function (UserStatus $status, bool $account, bool $content) {
    expect($status->allowsAccountWrites())->toBe($account)
        ->and($status->allowsContentWrites())->toBe($content);
})->with([
    'active' => [UserStatus::Active, true, true],
    'restricted' => [UserStatus::Restricted, true, false],
    'suspended' => [UserStatus::Suspended, false, false],
    'banned' => [UserStatus::Banned, false, false],
    'pending_deletion' => [UserStatus::PendingDeletion, false, false],
]);

it('gives impersonation to nobody', function () {
    expect(StaffAbility::Impersonate->minimumRole())->toBeNull();

    foreach (Role::cases() as $role) {
        expect(StaffAbility::Impersonate->grantedTo($role))->toBeFalse();
    }
});

it('gives every other staff ability to super admin and none to a user', function () {
    foreach (StaffAbility::cases() as $ability) {
        if ($ability === StaffAbility::Impersonate) {
            continue;
        }

        expect($ability->grantedTo(Role::SuperAdmin))->toBeTrue()
            ->and($ability->grantedTo(Role::User))->toBeFalse();
    }
});
