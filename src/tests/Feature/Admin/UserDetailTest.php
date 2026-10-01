<?php

use App\Domain\Audit\Models\AuditLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\Auth\CapturesSecurityLog;

// specs/12 §4 author context, FR-ADMIN-6 (support from data), specs/11 §3 (admin data access).

uses(CapturesSecurityLog::class);

beforeEach(function () {
    $this->captureSecurityLog();
    $this->admin = User::factory()->admin()->create(['username' => 'warden']);
});

function liveSession(User $user, string $id): void
{
    DB::table('sessions')->insert(['id' => $id, 'user_id' => $user->id, 'payload' => '', 'last_activity' => time()]);
}

it('shows the account with its effective status and no private fields', function () {
    $chief = User::factory()->suspended(now()->addDays(3), 'Harassment in comments')->create([
        'username' => 'chief',
        'email' => 'chief@example.com',
        'last_login_at' => '2026-09-30 08:00:00',
    ]);
    liveSession($chief, 'sess-a');
    liveSession($chief, 'sess-b');
    DB::table('sessions')->insert(['id' => 'sess-old', 'user_id' => $chief->id, 'payload' => '', 'last_activity' => now()->subDays(30)->getTimestamp()]);

    $this->actingAs($this->admin)->get('/admin/users/'.$chief->ulid)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Users/Show')
            ->where('meta.title', 'chief')
            ->where('user', [
                'ulid' => $chief->ulid,
                'username' => 'chief',
                'email' => 'chief@example.com',
                'emailVerifiedAt' => $chief->email_verified_at->toIso8601String(),
                'roleLabel' => 'User',
                'statusLabel' => 'Suspended',
                'statusTone' => 'danger',
                'statusReason' => 'Harassment in comments',
                'statusEndsAt' => $chief->status_expires_at->toIso8601String(),
                'joinedAt' => $chief->created_at->toIso8601String(),
                'lastSignInAt' => '2026-09-30T08:00:00+00:00',
                'activeSessions' => 2,
                'deletedAt' => null,
            ])
            ->has('displayName')
            ->where('avatarUrl', null)
            ->where('auditTrail', [])
            ->where('moreAuditEntries', false));
});

it('reads a passed suspension as active, without its old reason', function () {
    $chief = User::factory()->suspended(now()->subMinute())->create();

    $this->actingAs($this->admin)->get('/admin/users/'.$chief->ulid)
        ->assertInertia(fn (Assert $page) => $page
            ->where('user.statusLabel', 'Active')
            ->where('user.statusReason', null)
            ->where('user.statusEndsAt', null));
});

it('lists the latest audit entries about this account only, and says when there are more', function () {
    config(['platform.admin.audit_trail_limit' => 2]);
    $chief = User::factory()->create();
    $older = AuditLog::factory()->on($chief)->create();
    $middle = AuditLog::factory()->on($chief)->create();
    $latest = AuditLog::factory()->by($this->admin)->on($chief)->create();
    AuditLog::factory()->on($this->admin)->create();

    $this->actingAs($this->admin)->get('/admin/users/'.$chief->ulid)
        ->assertInertia(fn (Assert $page) => $page
            ->where('auditTrail.0.id', $latest->id)
            ->where('auditTrail.0.actorUsername', 'warden')
            ->where('auditTrail.1.id', $middle->id)
            ->has('auditTrail', 2)
            ->where('moreAuditEntries', true));

    expect($older->id)->toBeLessThan($middle->id);
});

it('opens a soft-deleted account', function () {
    $gone = User::factory()->create();
    $gone->delete();

    $this->actingAs($this->admin)->get('/admin/users/'.$gone->ulid)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('user.deletedAt', $gone->fresh()->deleted_at->toIso8601String()));
});

it('finds the account by an upper-case ULID too', function () {
    $chief = User::factory()->create();

    $this->actingAs($this->admin)->get('/admin/users/'.strtoupper($chief->ulid))->assertOk();
});

it('404s the viewer\'s own account and super admins, without logging a view', function () {
    $founder = User::factory()->superAdmin()->create();

    $this->actingAs($this->admin)->get('/admin/users/'.$this->admin->ulid)->assertNotFound();
    $this->actingAs($this->admin)->get('/admin/users/'.$founder->ulid)->assertNotFound();
    $this->actingAs(User::factory()->superAdmin()->create())->get('/admin/users/'.$founder->ulid)->assertNotFound();

    expect(array_column($this->securityEvents(), 'message'))->not->toContain('admin.user_viewed');
});

it('404s an unknown or malformed ULID', function (string $ulid) {
    $this->actingAs($this->admin)->get('/admin/users/'.$ulid)->assertNotFound();
})->with([
    'unknown' => ['01hzzzzzzzzzzzzzzzzzzzzzzz'],
    'malformed' => ['not-a-ulid'],
    'numeric id' => ['1'],
]);

it('logs the view as admin data access, once', function () {
    $chief = User::factory()->create();

    $this->actingAs($this->admin)->get('/admin/users/'.$chief->ulid)->assertOk();

    $views = array_values(array_filter($this->securityEvents(), fn (array $e) => $e['message'] === 'admin.user_viewed'));
    expect($views)->toHaveCount(1)
        ->and($views[0]['context']['actor'])->toBe($this->admin->ulid)
        ->and($views[0]['context']['user'])->toBe($chief->ulid)
        ->and($views[0]['context'])->toHaveKey('ip_hash');
});

it('does not log a 404', function () {
    $this->actingAs($this->admin)->get('/admin/users/01hzzzzzzzzzzzzzzzzzzzzzzz')->assertNotFound();

    expect(array_column($this->securityEvents(), 'message'))->not->toContain('admin.user_viewed');
});

it('reads its trail size from config', function () {
    expect(config('platform.admin.audit_trail_limit'))->toBe(10);
});

it('keeps the detail within the query budget', function () {
    $chief = User::factory()->create();
    AuditLog::factory()->count(15)->on($chief)->create();

    DB::enableQueryLog();
    $this->actingAs($this->admin)->get('/admin/users/'.$chief->ulid)->assertOk();

    expect(count(DB::getQueryLog()))->toBeLessThanOrEqual(25);
});
