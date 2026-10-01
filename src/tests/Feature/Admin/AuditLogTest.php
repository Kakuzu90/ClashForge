<?php

use App\Domain\Audit\Models\AuditLog;
use App\Http\Middleware\HandleInertiaRequests;
use App\Models\User;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;

// FR-ADMIN-4 (audit half): the read-only audit log with filters by actor, target, action and date.

beforeEach(function () {
    $this->admin = User::factory()->admin()->create(['username' => 'warden']);
});

/**
 * The deferred `log` prop, as the page's partial reload fetches it.
 *
 * @param  array<string, string>  $query
 */
function loadAuditLog(User $viewer, array $query = []): TestResponse
{
    $version = (string) app(HandleInertiaRequests::class)->version(request());

    return test()->actingAs($viewer)->withHeaders([
        'X-Inertia' => 'true',
        'X-Inertia-Version' => $version,
        'X-Inertia-Partial-Component' => 'Admin/AuditLog',
        'X-Inertia-Partial-Data' => 'log',
    ])->get('/admin/audit'.($query === [] ? '' : '?'.http_build_query($query)));
}

/**
 * @param  array<string, string>  $query
 * @return list<array<string, mixed>>
 */
function auditEntries(User $viewer, array $query = []): array
{
    return loadAuditLog($viewer, $query)->assertOk()->json('props.log.entries');
}

it('renders the page with its filters and loads the entries as a deferred prop', function () {
    $this->actingAs($this->admin)->get('/admin/audit?actor=warden&action=role.changed&from=2026-09-01&to=2026-09-30')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/AuditLog')
            ->where('meta.title', 'Audit log')
            ->where('filters', ['actor' => 'warden', 'target' => null, 'action' => 'role.changed', 'from' => '2026-09-01', 'to' => '2026-09-30'])
            ->where('actions', [['value' => 'role.changed', 'label' => 'Role changed']])
            ->where('auth.can.viewAuditLog', true)
            ->missing('log'));
});

it('lists entries newest first with usernames, labels and before/after', function () {
    $target = User::factory()->create(['username' => 'chief']);
    Date::setTestNow('2026-09-20 10:00:00');
    AuditLog::factory()->on($target)->create();
    Date::setTestNow('2026-09-21 10:00:00');
    $latest = AuditLog::factory()->by($this->admin)->on($target)->create([
        'before' => ['role' => 'moderator'],
        'after' => ['role' => 'user'],
        'context' => ['reason' => 'Stepped down'],
    ]);

    $entries = auditEntries($this->admin);

    expect($entries)->toHaveCount(2)
        ->and($entries[0])->toBe([
            'id' => $latest->id,
            'action' => 'role.changed',
            'actionLabel' => 'Role changed',
            'actorUsername' => 'warden',
            'actorRoleLabel' => 'Admin',
            'actorVia' => null,
            'subjectLabel' => 'Account',
            'subjectName' => 'chief',
            'before' => ['role' => 'moderator'],
            'after' => ['role' => 'user'],
            'context' => ['reason' => 'Stepped down'],
            'userAgent' => $latest->user_agent,
            'requestId' => $latest->request_id,
            'createdAt' => '2026-09-21T10:00:00+00:00',
        ])
        ->and($entries[1])->toMatchArray(['actorUsername' => null, 'actorRoleLabel' => null, 'actorVia' => 'console', 'subjectName' => 'chief']);
});

it('filters by actor and by target, ignoring case', function () {
    $chief = User::factory()->create(['username' => 'chief']);
    $other = User::factory()->create(['username' => 'other']);
    $mine = AuditLog::factory()->by($this->admin)->on($chief)->create();
    AuditLog::factory()->on($chief)->create();
    $onOther = AuditLog::factory()->on($other)->create();

    expect(array_column(auditEntries($this->admin, ['actor' => 'WARDEN']), 'id'))->toBe([$mine->id])
        ->and(array_column(auditEntries($this->admin, ['target' => 'Other']), 'id'))->toBe([$onOther->id])
        ->and(auditEntries($this->admin, ['actor' => 'nobody']))->toBe([]);
});

it('filters by action and by whole UTC days', function () {
    Date::setTestNow('2026-09-09 23:59:59');
    AuditLog::factory()->create();
    Date::setTestNow('2026-09-10 00:00:00');
    $first = AuditLog::factory()->create();
    Date::setTestNow('2026-09-11 23:59:59');
    $last = AuditLog::factory()->create();
    Date::setTestNow('2026-09-12 00:00:00');
    AuditLog::factory()->create();

    expect(array_column(auditEntries($this->admin, ['from' => '2026-09-10', 'to' => '2026-09-11']), 'id'))->toBe([$last->id, $first->id])
        ->and(auditEntries($this->admin, ['action' => 'role.changed']))->toHaveCount(4);
});

it('reads its page size from config', function () {
    expect(config('platform.admin.per_page'))->toBe(50);
});

it('pages with cursors, keeping the filters', function () {
    config(['platform.admin.per_page' => 2]);
    $ids = AuditLog::factory()->count(5)->create()->pluck('id')->reverse()->values()->all();

    $first = loadAuditLog($this->admin)->json('props.log');
    $second = loadAuditLog($this->admin, ['cursor' => $first['olderCursor']])->json('props.log');
    $third = loadAuditLog($this->admin, ['cursor' => $second['olderCursor']])->json('props.log');

    expect(array_column($first['entries'], 'id'))->toBe(array_slice($ids, 0, 2))
        ->and($first['newerCursor'])->toBeNull()
        ->and(array_column($second['entries'], 'id'))->toBe(array_slice($ids, 2, 2))
        ->and(array_column($third['entries'], 'id'))->toBe(array_slice($ids, 4, 1))
        ->and($third['olderCursor'])->toBeNull()
        ->and(array_column(loadAuditLog($this->admin, ['cursor' => $third['newerCursor']])->json('props.log.entries'), 'id'))->toBe(array_slice($ids, 2, 2));
});

it('shows an empty list when nothing is logged', function () {
    expect(loadAuditLog($this->admin)->json('props.log'))->toBe(['entries' => [], 'newerCursor' => null, 'olderCursor' => null]);
});

it('names an account that no longer exists as null', function () {
    AuditLog::factory()->create(['auditable_id' => 999_999]);

    expect(auditEntries($this->admin)[0]['subjectName'])->toBeNull();
});

it('rejects invalid filters', function (array $query, string $field) {
    $this->actingAs($this->admin)->get('/admin/audit?'.http_build_query($query))
        ->assertRedirect('/admin/audit')
        ->assertSessionHasErrors($field);
})->with([
    'unknown action' => [['action' => 'role.deleted'], 'action'],
    'bad date' => [['from' => '2026-02-30'], 'from'],
    'not a date' => [['to' => 'yesterday'], 'to'],
    'to before from' => [['from' => '2026-09-10', 'to' => '2026-09-01'], 'to'],
    'long username' => [['actor' => str_repeat('a', 21)], 'actor'],
    'array' => [['target' => ['a', 'b']], 'target'],
    'empty JSON cursor' => [['cursor' => 'e30'], 'cursor'],
    'scalar cursor' => [['cursor' => 'MQ'], 'cursor'],
    'non-integer id cursor' => [['cursor' => rtrim(strtr(base64_encode('{"_pointsToNextItems":true,"audit_logs.id":"abc"}'), '+/', '-_'), '=')], 'cursor'],
    'not base64 cursor' => [['cursor' => '%%%'], 'cursor'],
    'invalid UTF-8 actor' => [['actor' => "\xC3\x28"], 'actor'],
    'NUL byte target' => [['target' => "chi\0ef"], 'target'],
]);

it('keeps the list within the query budget', function () {
    AuditLog::factory()->count(30)->by($this->admin)->create();

    DB::enableQueryLog();
    loadAuditLog($this->admin)->assertOk();

    expect(count(DB::getQueryLog()))->toBeLessThanOrEqual(25);
});

it('shows the Logs nav item to admins only', function (string $role, bool $expected) {
    $this->actingAs(User::factory()->{$role}()->create())->get('/admin')
        ->assertInertia(fn (Assert $page) => $page->where('auth.can.viewAuditLog', $expected));
})->with([
    'moderator' => ['moderator', false],
    'admin' => ['admin', true],
    'super admin' => ['superAdmin', true],
]);
