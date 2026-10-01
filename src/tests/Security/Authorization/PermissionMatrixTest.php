<?php

use App\Domain\Auth\Enums\Role;
use App\Domain\Auth\Enums\StaffAbility;
use App\Domain\Media\Models\Media;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

// specs/11 §4: every role × every staff ability, asserted against the specs/04 §2 matrix as
// written there (user, moderator, admin, super admin). Changing a Gate means changing this table.

const MATRIX = [
    'access-admin' => [false, true, true, true],
    'view-users' => [false, false, true, true],
    'view-report-queue' => [false, true, true, true],
    'claim-report-case' => [false, true, true, true],
    'hide-content' => [false, true, true, true],
    'remove-content' => [false, false, true, true],
    'warn-user' => [false, true, true, true],
    'restrict-user' => [false, true, true, true],
    'suspend-user' => [false, false, true, true],
    'ban-user' => [false, false, true, true],
    'lift-sanction' => [false, false, true, true],
    'review-media-quarantine' => [false, true, true, true],
    'resolve-disputes' => [false, false, true, true],
    'force-ownership-transfer' => [false, false, true, true],
    'approve-sellers' => [false, false, true, true],
    'resolve-marketplace-disputes' => [false, false, true, true],
    'manage-tags' => [false, false, true, true],
    'view-moderation-log' => [false, true, true, true],
    'view-audit-log' => [false, false, true, true],
    'manage-roles' => [false, false, false, true],
    'manage-settings' => [false, false, false, true],
    'hard-delete-user' => [false, false, false, true],
    'impersonate' => [false, false, false, false],
];

it('covers every staff ability', function () {
    expect(array_keys(MATRIX))->toEqualCanonicalizing(StaffAbility::values());
});

it('grants each role exactly its matrix row', function (string $ability) {
    foreach (Role::cases() as $index => $role) {
        $user = User::factory()->create(['role' => $role]);

        expect(Gate::forUser($user)->allows($ability))
            ->toBe(MATRIX[$ability][$index], "{$role->value} → {$ability}");
    }
})->with(array_keys(MATRIX));

it('denies guests every staff ability', function () {
    foreach (StaffAbility::cases() as $ability) {
        expect(Gate::allows($ability->value))->toBeFalse();
    }
});

it('leaves a restricted or pending-deletion staff member the read abilities only', function (string $state) {
    $admin = User::factory()->superAdmin()->{$state}()->create();

    foreach (StaffAbility::cases() as $ability) {
        $expected = $ability->isReadOnly();

        expect(Gate::forUser($admin)->allows($ability->value))->toBe($expected, "{$state} → {$ability->value}");
    }
})->with(['restricted', 'pendingDeletion']);

it('takes every staff ability from a suspended account', function () {
    $admin = User::factory()->superAdmin()->suspended()->create();

    foreach (StaffAbility::cases() as $ability) {
        expect(Gate::forUser($admin)->allows($ability->value))->toBeFalse();
    }
});

it('marks exactly the admin area, user list, queue and logs as read abilities', function () {
    $reads = array_values(array_filter(StaffAbility::cases(), fn (StaffAbility $a): bool => $a->isReadOnly()));

    expect($reads)->toBe([StaffAbility::AccessAdmin, StaffAbility::ViewUsers, StaffAbility::ViewReportQueue, StaffAbility::ViewModerationLog, StaffAbility::ViewAuditLog]);
});

it('gives staff powers back once a timed sanction has passed', function () {
    $moderator = User::factory()->moderator()->suspended(now()->subMinute())->create();

    expect(Gate::forUser($moderator)->allows('access-admin'))->toBeTrue()
        ->and(Gate::forUser($moderator)->allows('hide-content'))->toBeTrue();
});

it('keeps ownership policies in force for a super admin', function () {
    $media = Media::factory()->create();
    $superAdmin = User::factory()->superAdmin()->create();

    expect(Gate::forUser($superAdmin)->allows('view', $media))->toBeFalse()
        ->and(Gate::forUser($superAdmin)->allows('complete', $media))->toBeFalse();
});
