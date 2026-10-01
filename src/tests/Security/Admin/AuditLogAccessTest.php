<?php

use App\Domain\Audit\Models\AuditLog;
use App\Http\Middleware\HandleInertiaRequests;
use App\Models\User;
use Illuminate\Testing\TestResponse;

// specs/04 §2 "View audit log" (admin+), §3 (read abilities stay open to restricted and
// pending-deletion staff), specs/11 "Data exposure via page props".

function partialAuditLog(User $viewer, string $query = ''): TestResponse
{
    return test()->actingAs($viewer)->withHeaders([
        'X-Inertia' => 'true',
        'X-Inertia-Version' => (string) app(HandleInertiaRequests::class)->version(request()),
        'X-Inertia-Partial-Component' => 'Admin/AuditLog',
        'X-Inertia-Partial-Data' => 'log',
    ])->get('/admin/audit'.$query);
}

it('sends a guest to sign in', function () {
    $this->get('/admin/audit')->assertRedirect('/login');
});

it('refuses users and moderators, the page and its deferred prop alike', function (string $role) {
    $viewer = User::factory()->{$role}()->create();

    $this->actingAs($viewer)->get('/admin/audit')->assertForbidden();
    partialAuditLog($viewer)->assertForbidden();
})->with(['unverified', 'moderator']);

it('refuses a moderator before validating the filters', function () {
    $this->actingAs(User::factory()->moderator()->create())->get('/admin/audit?action=nope')->assertForbidden();
});

it('opens for admins and super admins, including restricted and pending-deletion ones', function (string $role, ?string $state) {
    $factory = User::factory()->{$role}();
    $viewer = ($state === null ? $factory : $factory->{$state}())->create();

    $this->actingAs($viewer)->get('/admin/audit')->assertOk();
})->with([
    'admin' => ['admin', null],
    'super admin' => ['superAdmin', null],
    'restricted admin' => ['admin', 'restricted'],
    'pending-deletion admin' => ['admin', 'pendingDeletion'],
]);

it('sends a suspended admin to the notice', function () {
    $this->actingAs(User::factory()->admin()->suspended()->create())->get('/admin/audit')->assertRedirect('/account/suspended');
});

it('never puts the IP hash or a raw IP in the props', function () {
    $admin = User::factory()->admin()->create();
    AuditLog::factory()->by($admin)->create(['ip_hash' => str_repeat('f', 64), 'context' => ['via' => 'web']]);

    $body = partialAuditLog($admin)->assertOk()->getContent();

    expect($body)->not->toContain(str_repeat('f', 64))
        ->and($body)->not->toContain('ip_hash')
        ->and($body)->not->toContain('ipHash');
});

// Vue renders these values as text (tests/js/admin/AdminDiffViewer.test.ts covers the escaping).
it('passes stored markup through as plain data', function () {
    $admin = User::factory()->admin()->create();
    $payload = '<img src=x onerror=alert(1)>';
    AuditLog::factory()->by($admin)->create(['before' => ['note' => $payload], 'after' => ['note' => $payload], 'user_agent' => $payload]);

    $entry = partialAuditLog($admin)->assertOk()->json('props.log.entries.0');

    expect($entry['before'])->toBe(['note' => $payload])
        ->and($entry['userAgent'])->toBe($payload);
});
