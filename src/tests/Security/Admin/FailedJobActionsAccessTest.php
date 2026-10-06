<?php

use App\Domain\Audit\Models\AuditLog;
use App\Http\Middleware\HandleInertiaRequests;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\Auth\CapturesSecurityLog;

// P2-19: `manage-failed-jobs` is admin+ with an active account (specs/04 §2–3); every action is
// rate limited (`admin-failed-jobs`) and CSRF-protected, and no payload or exception text reaches
// the page or the audit log (specs/11).

uses(CapturesSecurityLog::class);

beforeEach(function () {
    DB::table('failed_jobs')->insert([
        'uuid' => $this->uuid = (string) Str::uuid(), 'connection' => 'database', 'queue' => 'default',
        'payload' => json_encode(['displayName' => 'App\\Jobs\\SendMail', 'data' => ['command' => 'O:8:"stdClass":1:{s:5:"email";s:16:"pat@example.test";}']]),
        'exception' => 'RuntimeException: token sk_live_secret leaked', 'failed_at' => now(),
    ]);
});

it('refuses retry and delete to everyone but admins with an active account', function (?string $role, ?string $state, int $status) {
    $factory = $role === null ? User::factory() : User::factory()->{$role}();
    $viewer = ($state === null ? $factory : $factory->{$state}())->create();

    $retry = $this->actingAs($viewer)->post('/admin/system/failed-jobs/retry', ['uuid' => $this->uuid]);
    $delete = $this->actingAs($viewer)->delete('/admin/system/failed-jobs', ['uuid' => $this->uuid]);

    expect($retry->status())->toBe($status)->and($delete->status())->toBe($status)
        ->and(DB::table('failed_jobs')->count())->toBe(1)
        ->and(AuditLog::query()->count())->toBe(0);
})->with([
    'user' => [null, null, 403],
    'moderator' => ['moderator', null, 403],
    'restricted admin' => ['admin', 'restricted', 403],
    'suspended admin' => ['admin', 'suspended', 302],
    'leaving admin' => ['admin', 'pendingDeletion', 403],
]);

it('lets an admin and a super admin act', function (string $role) {
    $this->actingAs(User::factory()->{$role}()->create())->from('/admin/system')
        ->delete('/admin/system/failed-jobs', ['uuid' => $this->uuid])->assertSessionHas('success');

    expect(DB::table('failed_jobs')->count())->toBe(0);
})->with(['admin', 'superAdmin']);

it('hides the actions from staff who may read the page but not act', function () {
    $this->actingAs(User::factory()->admin()->restricted()->create())->get('/admin/system')
        ->assertInertia(fn ($page) => $page->where('canManageFailedJobs', false));
});

it('keeps guests out', function () {
    $this->post('/admin/system/failed-jobs/retry', ['uuid' => $this->uuid])->assertRedirect('/login');
    $this->delete('/admin/system/failed-jobs', ['uuid' => $this->uuid])->assertRedirect('/login');
});

it('needs the CSRF token on retry and delete', function () {
    $admin = User::factory()->admin()->create();
    $this->app['env'] = 'production';

    $this->actingAs($admin)->post('/admin/system/failed-jobs/retry', ['uuid' => $this->uuid])->assertStatus(419);
    $this->actingAs($admin)->delete('/admin/system/failed-jobs', ['uuid' => $this->uuid])->assertStatus(419);
    expect(DB::table('failed_jobs')->count())->toBe(1);
});

it('limits actions per staff member (admin-failed-jobs)', function () {
    $this->captureSecurityLog();
    config(['platform.rate_limits.admin_failed_jobs_per_minute' => 1]);
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->from('/admin/system')->post('/admin/system/failed-jobs/retry', ['class' => 'App\\Jobs\\Missing'])->assertSessionHasErrors('target');
    $this->actingAs($admin)->from('/admin/system')->delete('/admin/system/failed-jobs', ['uuid' => $this->uuid])
        ->assertSessionHas('error', 'Too many failed-job actions. Wait a minute and try again.');

    expect(DB::table('failed_jobs')->count())->toBe(1)
        ->and(collect($this->securityEvents())->contains(fn (array $e) => ($e['context']['limiter'] ?? null) === 'admin-failed-jobs'))->toBeTrue();
    $this->actingAs(User::factory()->admin()->create())->from('/admin/system')
        ->delete('/admin/system/failed-jobs', ['uuid' => $this->uuid])->assertSessionHas('success');
});

it('never puts a payload or exception text in the job list or the audit entry', function () {
    $admin = User::factory()->admin()->create();
    $list = test()->actingAs($admin)->withHeaders([
        'X-Inertia' => 'true',
        'X-Inertia-Version' => (string) app(HandleInertiaRequests::class)->version(request()),
        'X-Inertia-Partial-Component' => 'Admin/System',
        'X-Inertia-Partial-Data' => 'failedJobList',
    ])->get('/admin/system?jobsClass='.urlencode('App\\Jobs\\SendMail'))->json('props.failedJobList');

    $this->actingAs($admin)->from('/admin/system')->delete('/admin/system/failed-jobs', ['uuid' => $this->uuid]);

    expect($list['jobs'][0]['uuid'])->toBe($this->uuid);
    foreach ([json_encode($list), json_encode(AuditLog::query()->sole()->toArray())] as $text) {
        expect($text)->not->toContain('pat@example.test')->not->toContain('sk_live_secret')->not->toContain('RuntimeException');
    }
});
