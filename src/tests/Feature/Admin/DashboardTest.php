<?php

use App\Domain\Media\Enums\MediaCollection;
use App\Domain\Media\Models\Media;
use App\Domain\Media\Models\MediaVariant;
use App\Http\Middleware\HandleInertiaRequests;
use App\Models\User;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;

// FR-ADMIN-1: /admin is for staff only. FR-ADMIN-5: sign-ups, failed jobs and media storage,
// admin and above (`view-platform-stats`).

beforeEach(function () {
    Date::setTestNow('2026-10-01 12:00:00');
    // Staff accounts are older than every window, so they never count as sign-ups.
    $this->admin = User::factory()->admin()->create(['created_at' => now()->subDays(90)]);
});

function loadPanel(User $viewer, string $prop): TestResponse
{
    return test()->actingAs($viewer)->withHeaders([
        'X-Inertia' => 'true',
        'X-Inertia-Version' => (string) app(HandleInertiaRequests::class)->version(request()),
        'X-Inertia-Partial-Component' => 'Admin/Dashboard',
        'X-Inertia-Partial-Data' => $prop,
    ])->get('/admin');
}

function failJob(string $at, ?string $payload = null): void
{
    DB::table((string) config('queue.failed.table'))->insert([
        'uuid' => (string) Str::uuid(),
        'connection' => 'database',
        'queue' => 'default',
        'payload' => $payload ?? json_encode(['displayName' => 'App\\Domain\\Media\\Jobs\\ProcessMediaJob', 'data' => ['email' => 'secret@example.com']]),
        'exception' => 'RuntimeException: token sk_live_private in /var/www',
        'failed_at' => $at,
    ]);
}

it('sends a guest to sign in', function () {
    $this->get('/admin')->assertRedirect('/login');
});

it('refuses a plain user', function () {
    $this->actingAs(User::factory()->create())->get('/admin')->assertForbidden();
});

it('still opens for a restricted moderator, who keeps read access', function () {
    $this->actingAs(User::factory()->moderator()->restricted()->create())->get('/admin')->assertOk();
});

it('sends a suspended moderator to the notice', function () {
    $this->actingAs(User::factory()->moderator()->suspended()->create())->get('/admin')->assertRedirect('/account/suspended');
});

it('renders the dashboard for staff', function (string $role) {
    $this->actingAs(User::factory()->{$role}()->create())
        ->get('/admin')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Dashboard')
            ->where('meta.title', 'Admin')
            ->where('auth.can.accessAdmin', true)
        );
})->with(['moderator', 'admin', 'superAdmin']);

it('gives admins the three panels as separate deferred props', function () {
    $this->actingAs($this->admin)->get('/admin')
        ->assertInertia(fn (Assert $page) => $page
            ->where('platformStats', true)
            ->missing('signups')
            ->missing('failedJobs')
            ->missing('storage'));

    $deferred = $this->actingAs($this->admin)->get('/admin')->viewData('page')['deferredProps'];

    expect($deferred)->toBe(['signups' => ['signups'], 'failedJobs' => ['failedJobs'], 'storage' => ['storage']]);
});

it('gives moderators no panels at all', function () {
    $moderator = User::factory()->moderator()->create();

    $this->actingAs($moderator)->get('/admin')
        ->assertInertia(fn (Assert $page) => $page->where('platformStats', false));

    expect($this->actingAs($moderator)->get('/admin')->viewData('page'))->not->toHaveKey('deferredProps');

    foreach (['signups', 'failedJobs', 'storage'] as $prop) {
        // Inertia answers a requested prop the page does not have with null.
        expect(loadPanel($moderator, $prop)->assertOk()->json("props.{$prop}"))->toBeNull();
    }
});

it('counts sign-ups per window at the edges, deleted and unverified ones included', function () {
    User::factory()->create(['created_at' => now()->subDay()]);
    User::factory()->unverified()->create(['created_at' => now()->subHours(2)]);
    User::factory()->create(['created_at' => now()->subDay()->subSecond()]);
    User::factory()->create(['created_at' => now()->subDays(7)])->delete();
    User::factory()->unverified()->create(['created_at' => now()->subDays(30)]);
    User::factory()->create(['created_at' => now()->subDays(30)->subSecond()]);

    expect(loadPanel($this->admin, 'signups')->assertOk()->json('props.signups'))->toBe([
        'last24Hours' => ['total' => 2, 'verified' => 1],
        'last7Days' => ['total' => 4, 'verified' => 3],
        'last30Days' => ['total' => 5, 'verified' => 3],
    ]);
});

it('counts failed jobs per hour and day, and flags the alert line', function () {
    $alert = (int) config('platform.admin.failed_jobs_alert_per_hour');

    foreach (range(1, $alert) as $_) {
        failJob(now()->subMinutes(30)->toDateTimeString());
    }
    failJob(now()->subHours(5)->toDateTimeString());
    failJob(now()->subDays(2)->toDateTimeString());

    $summary = loadPanel($this->admin, 'failedJobs')->json('props.failedJobs');

    expect($summary['lastHour'])->toBe($alert)
        ->and($summary['last24Hours'])->toBe($alert + 1)
        ->and($summary['alertPerHour'])->toBe($alert)
        ->and($summary['overThreshold'])->toBeFalse()
        ->and($summary['topClasses'])->toBe([[
            'name' => 'App\\Domain\\Media\\Jobs\\ProcessMediaJob',
            'count' => $alert + 1,
            'lastFailedAt' => now()->subMinutes(30)->toIso8601String(),
        ]]);

    failJob(now()->subMinute()->toDateTimeString());

    expect(loadPanel($this->admin, 'failedJobs')->json('props.failedJobs.overThreshold'))->toBeTrue();
});

it('counts failed jobs at the window edges, and breaks ties by the latest failure', function () {
    $job = fn (string $name) => json_encode(['displayName' => $name]);

    failJob(now()->subHour()->toDateTimeString(), $job('App\\Jobs\\Older'));
    failJob(now()->subHour()->subSecond()->toDateTimeString(), $job('App\\Jobs\\Older'));
    failJob(now()->subDay()->toDateTimeString(), $job('App\\Jobs\\Newer'));
    failJob(now()->subMinute()->toDateTimeString(), $job('App\\Jobs\\Newer'));
    failJob(now()->subDay()->subSecond()->toDateTimeString(), $job('App\\Jobs\\Outside'));

    $summary = loadPanel($this->admin, 'failedJobs')->json('props.failedJobs');

    expect($summary['lastHour'])->toBe(2)
        ->and($summary['last24Hours'])->toBe(4)
        ->and(array_column($summary['topClasses'], 'name'))->toBe(['App\\Jobs\\Newer', 'App\\Jobs\\Older'])
        ->and(array_column($summary['topClasses'], 'count'))->toBe([2, 2]);
});

it('lists the most failed job classes up to the limit, an unreadable payload as a class of its own', function () {
    $limit = (int) config('platform.admin.failed_jobs_top_classes');

    foreach (range(1, $limit + 1) as $i) {
        foreach (range(1, $i) as $_) {
            failJob(now()->subMinutes($i)->toDateTimeString(), json_encode(['displayName' => "App\\Jobs\\Job{$i}"]));
        }
    }
    failJob(now()->subMinutes(3)->toDateTimeString(), 'not json at all');
    failJob(now()->subMinutes(3)->toDateTimeString(), '["a list"]');

    $classes = loadPanel($this->admin, 'failedJobs')->assertOk()->json('props.failedJobs.topClasses');

    expect($classes)->toHaveCount($limit)
        ->and(array_column($classes, 'name'))->toBe(array_map(fn (int $i) => "App\\Jobs\\Job{$i}", range($limit + 1, 2)))
        ->and(loadPanel($this->admin, 'failedJobs')->json('props.failedJobs.last24Hours'))->toBe(array_sum(range(1, $limit + 1)) + 2);
});

it('says so when no job failed in the last day', function () {
    expect(loadPanel($this->admin, 'failedJobs')->json('props.failedJobs'))->toMatchArray([
        'lastHour' => 0,
        'last24Hours' => 0,
        'overThreshold' => false,
        'topClasses' => [],
    ]);
});

it('sums originals and variants per collection, with deleted media apart and pending media left out', function () {
    $avatar = Media::factory()->collection(MediaCollection::Avatar)->ready()->create(['size_bytes' => 1_000]);
    MediaVariant::factory()->for($avatar)->create(['size_bytes' => 200]);
    Media::factory()->ready()->create(['size_bytes' => 5_000]);
    Media::factory()->create(['size_bytes' => 9_999_999]);
    Media::factory()->quarantined()->create(['size_bytes' => 3_000]);
    // Held for review although its parent is gone: never purged by the 7-day job (specs/10 §9).
    Media::factory()->quarantined()->create(['size_bytes' => 700])->delete();
    $deleted = Media::factory()->ready()->create(['size_bytes' => 4_000]);
    MediaVariant::factory()->for($deleted)->create(['size_bytes' => 400]);
    $deleted->delete();

    $storage = loadPanel($this->admin, 'storage')->assertOk()->json('props.storage');
    $collections = collect($storage['collections'])->keyBy('collection');

    expect($storage)->toMatchArray([
        'totalBytes' => 1_000 + 200 + 5_000 + 3_000 + 700 + 4_000 + 400,
        'totalObjects' => 7,
        'awaitingPurgeBytes' => 4_400,
        'awaitingPurgeObjects' => 2,
        'purgeAfterDays' => (int) config('media.lifecycle.purge_after_days'),
        'quarantinedCount' => 2,
    ])
        ->and($collections->keys()->all())->toBe(MediaCollection::values())
        ->and($collections['avatar'])->toBe(['collection' => 'avatar', 'label' => 'Avatar', 'bytes' => 1_200, 'objects' => 2])
        ->and($collections['base_screenshot'])->toMatchArray(['bytes' => 8_700, 'objects' => 3])
        ->and($collections['base_video'])->toMatchArray(['bytes' => 0, 'objects' => 0]);
});

it('keeps each panel within the query budget', function (string $prop) {
    User::factory()->count(5)->create();
    Media::factory()->count(5)->ready()->create();
    failJob(now()->subMinute()->toDateTimeString());

    DB::enableQueryLog();
    loadPanel($this->admin, $prop)->assertOk();

    expect(count(DB::getQueryLog()))->toBeLessThanOrEqual(15);
})->with(['signups', 'failedJobs', 'storage']);

it('reads its limits from config', function () {
    expect(config('platform.admin.failed_jobs_alert_per_hour'))->toBe(20)
        ->and(config('platform.admin.failed_jobs_top_classes'))->toBe(5);
});
