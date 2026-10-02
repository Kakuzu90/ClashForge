<?php

use App\Domain\Media\Enums\MediaCollection;
use App\Domain\Media\Enums\VariantName;
use App\Domain\Media\Models\Media;
use App\Domain\Media\Models\MediaVariant;
use App\Domain\Users\Models\Profile;
use App\Http\Middleware\HandleInertiaRequests;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\Auth\CapturesSecurityLog;

// FR-ADMIN-2 (users, read): every account, newest first, found by username prefix or email,
// filtered by role and effective status (specs/04 §3, specs/23 §7).

uses(CapturesSecurityLog::class);

beforeEach(function () {
    $this->captureSecurityLog();
    $this->admin = User::factory()->admin()->create(['username' => 'warden', 'email' => 'warden@example.com']);
});

/**
 * @param  array<string, string>  $query
 */
function loadUsers(User $viewer, array $query = []): TestResponse
{
    return test()->actingAs($viewer)->withHeaders([
        'X-Inertia' => 'true',
        'X-Inertia-Version' => (string) app(HandleInertiaRequests::class)->version(request()),
        'X-Inertia-Partial-Component' => 'Admin/Users/Index',
        'X-Inertia-Partial-Data' => 'users',
    ])->get('/admin/users'.($query === [] ? '' : '?'.http_build_query($query)));
}

/**
 * @param  array<string, string>  $query
 * @return list<string>
 */
function listedUsernames(User $viewer, array $query = []): array
{
    return array_column(loadUsers($viewer, $query)->assertOk()->json('props.users.entries'), 'username');
}

it('renders the page with its filters and loads the rows as a deferred prop', function () {
    $this->actingAs($this->admin)->get('/admin/users?search=chi&role=moderator&status=suspended')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Users/Index')
            ->where('meta.title', 'Users')
            ->where('filters', ['search' => 'chi', 'role' => 'moderator', 'status' => 'suspended'])
            ->where('roles', [['value' => 'user', 'label' => 'User'], ['value' => 'moderator', 'label' => 'Moderator'], ['value' => 'admin', 'label' => 'Admin']])
            ->where('statuses.2', ['value' => 'suspended', 'label' => 'Suspended'])
            ->where('auth.can.viewUsers', true)
            ->missing('users'));
});

it('lists every account newest first, with labels and no private fields', function () {
    $chief = User::factory()->suspended(now()->addDays(3))->create(['username' => 'chief', 'email' => 'chief@example.com', 'last_login_at' => '2026-09-30 08:00:00']);

    $rows = loadUsers($this->admin)->json('props.users.entries');

    expect(array_column($rows, 'username'))->toBe(['chief'])
        ->and($rows[0])->toBe([
            'ulid' => $chief->ulid,
            'username' => 'chief',
            'avatarUrl' => null,
            'email' => 'chief@example.com',
            'emailVerified' => true,
            'roleLabel' => 'User',
            'statusLabel' => 'Suspended',
            'statusTone' => 'danger',
            'joinedAt' => $chief->created_at->toIso8601String(),
            'lastSignInAt' => '2026-09-30T08:00:00+00:00',
            'deleted' => false,
        ]);
});

it('includes banned, pending-deletion and soft-deleted accounts', function () {
    User::factory()->banned()->create(['username' => 'banned_one']);
    User::factory()->pendingDeletion()->create(['username' => 'leaving']);
    $gone = User::factory()->create(['username' => 'gone']);
    $gone->delete();

    $rows = collect(loadUsers($this->admin)->json('props.users.entries'))->keyBy('username');

    expect($rows->keys()->all())->toBe(['gone', 'leaving', 'banned_one'])
        ->and($rows['gone']['deleted'])->toBeTrue()
        ->and($rows['banned_one']['statusLabel'])->toBe('Banned');
});

it('never lists the viewer or a super admin', function () {
    User::factory()->superAdmin()->create(['username' => 'founder']);
    User::factory()->create(['username' => 'chief']);

    expect(listedUsernames($this->admin))->toBe(['chief'])
        ->and(listedUsernames($this->admin, ['search' => 'warden']))->toBe([])
        ->and(listedUsernames($this->admin, ['search' => 'founder']))->toBe([]);

    // A super admin does not see themselves or other super admins either.
    $founder2 = User::factory()->superAdmin()->create(['username' => 'founder_two']);
    expect(listedUsernames($founder2))->toEqualCanonicalizing(['chief', 'warden']);
});

it('searches by username prefix, treating LIKE wildcards literally', function () {
    User::factory()->create(['username' => 'clash_king']);
    User::factory()->create(['username' => 'clashxking']);
    User::factory()->create(['username' => 'Clashy']);
    User::factory()->create(['username' => 'big_clash']);

    expect(listedUsernames($this->admin, ['search' => 'CLASH']))->toEqualCanonicalizing(['clash_king', 'clashxking', 'Clashy'])
        ->and(listedUsernames($this->admin, ['search' => 'clash_']))->toBe(['clash_king'])
        ->and(listedUsernames($this->admin, ['search' => '%']))->toBe([]);
});

it('searches by exact email, ignoring case', function () {
    User::factory()->create(['username' => 'chief', 'email' => 'chief@example.com']);
    User::factory()->create(['username' => 'other', 'email' => 'chief@example.com.au']);

    expect(listedUsernames($this->admin, ['search' => 'Chief@Example.com']))->toBe(['chief'])
        ->and(listedUsernames($this->admin, ['search' => '@example.com']))->toBe([]);
});

it('filters by role', function () {
    User::factory()->moderator()->create(['username' => 'mod']);
    User::factory()->admin()->create(['username' => 'other_admin']);

    expect(listedUsernames($this->admin, ['role' => 'moderator']))->toBe(['mod'])
        ->and(listedUsernames($this->admin, ['role' => 'admin']))->toBe(['other_admin']);
});

it('filters by effective status, so a passed suspension counts as active', function () {
    User::factory()->suspended(now()->addDay())->create(['username' => 'still_out']);
    User::factory()->suspended(now()->subMinute())->create(['username' => 'back_now']);
    User::factory()->restricted(now()->subMinute())->create(['username' => 'unrestricted']);
    User::factory()->banned()->create(['username' => 'banned_one']);

    expect(listedUsernames($this->admin, ['status' => 'suspended']))->toBe(['still_out'])
        ->and(listedUsernames($this->admin, ['status' => 'active']))->toEqualCanonicalizing(['back_now', 'unrestricted'])
        ->and(listedUsernames($this->admin, ['status' => 'banned']))->toBe(['banned_one']);

    $row = collect(loadUsers($this->admin)->json('props.users.entries'))->firstWhere('username', 'back_now');
    expect($row['statusLabel'])->toBe('Active');
});

it('pages with cursors', function () {
    config(['platform.admin.per_page' => 2]);
    User::factory()->count(4)->create();

    $first = loadUsers($this->admin)->json('props.users');
    $second = loadUsers($this->admin, ['cursor' => $first['olderCursor']])->json('props.users');

    expect($first['entries'])->toHaveCount(2)
        ->and($first['newerCursor'])->toBeNull()
        ->and($second['entries'])->toHaveCount(2)
        ->and($second['olderCursor'])->toBeNull()
        ->and(array_intersect(array_column($first['entries'], 'ulid'), array_column($second['entries'], 'ulid')))->toBe([]);
});

it('shows an empty list when nothing matches', function () {
    expect(loadUsers($this->admin, ['search' => 'nobody'])->json('props.users'))->toBe(['entries' => [], 'newerCursor' => null, 'olderCursor' => null]);
});

it('rejects invalid filters', function (array $query, string $field) {
    $this->actingAs($this->admin)->get('/admin/users?'.http_build_query($query))
        ->assertRedirect('/admin/users')
        ->assertSessionHasErrors($field);
})->with([
    'unknown role' => [['role' => 'owner'], 'role'],
    'super admin role' => [['role' => 'super_admin'], 'role'],
    'unknown status' => [['status' => 'frozen'], 'status'],
    'long search' => [['search' => str_repeat('a', 255)], 'search'],
    'array search' => [['search' => ['a']], 'search'],
    'bad cursor' => [['cursor' => 'e30'], 'cursor'],
    'invalid UTF-8' => [['search' => "\xC3\x28"], 'search'],
    'NUL byte' => [['search' => "chi\0ef"], 'search'],
]);

it('logs each load of the rows as admin data access, without the search text', function () {
    User::factory()->moderator()->create(['username' => 'chief', 'email' => 'chief@example.com']);

    $this->actingAs($this->admin)->get('/admin/users?search=chief@example.com')->assertOk();
    expect(array_column($this->securityEvents(), 'message'))->not->toContain('admin.users_listed');

    loadUsers($this->admin, ['search' => 'chief@example.com', 'role' => 'moderator'])->assertOk();

    $lines = array_values(array_filter($this->securityEvents(), fn (array $e) => $e['message'] === 'admin.users_listed'));
    expect($lines)->toHaveCount(1)
        ->and($lines[0]['context'])->toMatchArray([
            'actor' => $this->admin->ulid,
            'searched' => true,
            'role' => 'moderator',
            'status' => null,
            'paged' => false,
            'rows' => 1,
        ])
        ->and($lines[0]['context'])->toHaveKey('ip_hash')
        ->and(json_encode($lines[0]))->not->toContain('chief@example.com');
});

it('keeps the list within the query budget', function () {
    User::factory()->count(30)->create();

    DB::enableQueryLog();
    loadUsers($this->admin)->assertOk();

    expect(count(DB::getQueryLog()))->toBeLessThanOrEqual(25);
});

it('shows each account\'s ready avatar, within the query budget', function () {
    $withAvatar = User::factory()->create(['username' => 'pictured']);
    $media = Media::factory()->collection(MediaCollection::Avatar)->ready()->create(['user_id' => $withAvatar->id]);
    MediaVariant::factory()->create(['media_id' => $media->id, 'variant' => VariantName::Thumb->value, 'path' => "public/avatar/{$media->ulid}/thumb.webp", 'width' => 48, 'height' => 48]);
    Profile::query()->whereKey($withAvatar->id)->update(['avatar_media_id' => $media->id]);
    User::factory()->count(20)->create();

    DB::enableQueryLog();
    $rows = collect(loadUsers($this->admin)->json('props.users.entries'))->keyBy('username');

    expect($rows['pictured']['avatarUrl'])->toEndWith("public/avatar/{$media->ulid}/thumb.webp")
        ->and($rows->except('pictured')->pluck('avatarUrl')->filter()->all())->toBe([])
        ->and(count(DB::getQueryLog()))->toBeLessThanOrEqual(25);
});

it('shows the Users nav item to admins only', function (string $role, bool $expected) {
    $this->actingAs(User::factory()->{$role}()->create())->get('/')
        ->assertInertia(fn (Assert $page) => $page->where('auth.can.viewUsers', $expected));
})->with([
    'moderator' => ['moderator', false],
    'admin' => ['admin', true],
    'super admin' => ['superAdmin', true],
]);
