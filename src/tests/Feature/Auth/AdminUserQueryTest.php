<?php

use App\Domain\Auth\Data\AdminUserFilterData;
use App\Domain\Auth\Enums\UserStatus;
use App\Domain\Auth\Queries\AdminUserQuery;
use App\Models\User;
use Illuminate\Support\Facades\Date;

// The status filter mirrors UserStatus::effective() in SQL (specs/23 §7): a timed restriction or
// suspension whose end is now or earlier counts as active. Checked row by row against the enum.

beforeEach(fn () => Date::setTestNow('2026-10-01 12:00:00'));

it('filters by effective status exactly as UserStatus::effective() reads each row', function () {
    $now = Date::now();
    $rows = [
        'active' => [UserStatus::Active, null],
        'suspended_future' => [UserStatus::Suspended, $now->addSecond()],
        'suspended_now' => [UserStatus::Suspended, $now],
        'suspended_past' => [UserStatus::Suspended, $now->subSecond()],
        'suspended_open' => [UserStatus::Suspended, null],
        'restricted_future' => [UserStatus::Restricted, $now->addDay()],
        'restricted_now' => [UserStatus::Restricted, $now],
        'restricted_open' => [UserStatus::Restricted, null],
        'banned' => [UserStatus::Banned, null],
        // A ban never expires, even with a stale end date.
        'banned_dated' => [UserStatus::Banned, $now->subDay()],
        'leaving' => [UserStatus::PendingDeletion, null],
    ];

    foreach ($rows as $username => [$status, $until]) {
        User::factory()->create(['username' => $username, 'status' => $status, 'status_expires_at' => $until]);
    }

    $query = app(AdminUserQuery::class);
    $viewer = User::factory()->superAdmin()->create(['username' => 'viewer']);

    foreach (UserStatus::cases() as $filter) {
        $listed = array_column($query->page($viewer, new AdminUserFilterData(status: $filter), 100)->entries, 'username');
        $expected = array_keys(array_filter($rows, fn (array $row): bool => $row[0]->effective($row[1]?->toImmutable()) === $filter));

        expect($listed)->toEqualCanonicalizing($expected, $filter->value);
    }
});
