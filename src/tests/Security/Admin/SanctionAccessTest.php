<?php

use App\Domain\Auth\Enums\Role;
use App\Domain\Auth\Enums\UserStatus;
use App\Domain\Moderation\Data\ApplySanctionData;
use App\Domain\Moderation\Enums\ReasonCode;
use App\Domain\Moderation\Models\UserSanction;
use App\Domain\Moderation\Services\SanctionService;
use App\Models\User;
use Illuminate\Support\Facades\Notification;

// specs/04 §2 (suspend, ban, lift: admin+), rule 1 (strictly outrank the target), §3 (staff
// actions need an active account), specs/11 "Mass assignment".

beforeEach(fn () => Notification::fake());

function sanctionPayload(): array
{
    return ['reason_code' => 'spam', 'days' => 3, 'public_reason' => 'Spam links', 'internal_note' => 'Posted the same link 40 times.'];
}

function sanctionRequests(User $viewer, User $target): array
{
    $base = '/admin/users/'.$target->ulid;

    return [
        'suspend' => test()->actingAs($viewer)->post($base.'/suspension', sanctionPayload())->getStatusCode(),
        'ban' => test()->actingAs($viewer)->post($base.'/ban', sanctionPayload())->getStatusCode(),
        'lift' => test()->actingAs($viewer)->delete($base.'/sanction', ['note' => 'Lift.'])->getStatusCode(),
    ];
}

it('refuses users and moderators before validating', function (string $role) {
    $target = User::factory()->create();

    expect(sanctionRequests(User::factory()->{$role}()->create(), $target))->toBe(['suspend' => 403, 'ban' => 403, 'lift' => 403])
        ->and($target->fresh()->status)->toBe(UserStatus::Active);
})->with(['unverified', 'moderator']);

it('refuses admins whose own standing does not allow staff actions', function (string $state, int $status) {
    $target = User::factory()->create();
    $viewer = User::factory()->admin()->{$state}()->create();

    expect(array_unique(array_values(sanctionRequests($viewer, $target))))->toBe([$status])
        ->and(UserSanction::query()->count())->toBe(0);
})->with([
    'restricted' => ['restricted', 403],
    'pending deletion' => ['pendingDeletion', 403],
    'suspended' => ['suspended', 302],
]);

it('lets an admin act only on accounts they strictly outrank, on every route', function (string $route, Role $targetRole, bool $allowed) {
    $actor = User::factory()->admin()->create();
    $target = User::factory()->create(['role' => $targetRole]);
    if ($route === 'sanction') {
        // Something to lift, issued by a super admin, who outranks everyone here.
        app(SanctionService::class)->suspend(User::factory()->superAdmin()->create(), $target, new ApplySanctionData(ReasonCode::Spam, 'Spam', 'Note.', 3));
    }
    $before = $target->fresh()->status;

    $response = $route === 'sanction'
        ? test()->actingAs($actor)->delete('/admin/users/'.$target->ulid.'/sanction', ['note' => 'Lift.'])
        : test()->actingAs($actor)->post('/admin/users/'.$target->ulid.'/'.$route, sanctionPayload());

    $after = $target->fresh()->status;
    expect($response->getStatusCode())->toBe($allowed ? 302 : 403)
        ->and($after === $before)->toBe(! $allowed);
})->with([
    'suspension', 'ban', 'sanction',
])->with([
    'user' => [Role::User, true],
    'moderator' => [Role::Moderator, true],
    'admin' => [Role::Admin, false],
]);

it('lets a super admin sanction an admin', function () {
    $target = User::factory()->admin()->create();

    test()->actingAs(User::factory()->superAdmin()->create())->post('/admin/users/'.$target->ulid.'/ban', sanctionPayload())->assertRedirect();

    expect($target->fresh()->status)->toBe(UserStatus::Banned);
});

it('ignores privileged fields posted with a sanction', function () {
    $target = User::factory()->create();
    $admin = User::factory()->admin()->create();
    $other = User::factory()->superAdmin()->create();

    test()->actingAs($admin)->post('/admin/users/'.$target->ulid.'/suspension', [
        ...sanctionPayload(),
        'status' => 'active',
        'role' => 'admin',
        'issued_by' => $other->id,
        'expires_at' => '2099-01-01',
        'lifted_at' => now()->toIso8601String(),
    ])->assertRedirect();

    $sanction = UserSanction::query()->sole();
    expect($target->fresh()->role)->toBe(Role::User)
        ->and($target->fresh()->status)->toBe(UserStatus::Suspended)
        ->and($sanction->issued_by)->toBe($admin->id)
        ->and($sanction->lifted_at)->toBeNull()
        ->and($sanction->expires_at->diffInDays($sanction->starts_at, true))->toEqual(3);
});

it('stores markup in the message and note as plain text', function () {
    $target = User::factory()->create();
    $payload = '<img src=x onerror=alert(1)>';

    test()->actingAs(User::factory()->admin()->create())->post('/admin/users/'.$target->ulid.'/ban', [...sanctionPayload(), 'public_reason' => $payload, 'internal_note' => $payload])->assertRedirect();

    expect(UserSanction::query()->sole()->public_reason)->toBe($payload)
        ->and($target->fresh()->status_reason)->toBe($payload);
});
