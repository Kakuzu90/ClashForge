<?php

use App\Domain\CocIntegration\Support\CocApiKey;
use App\Http\Middleware\HandleInertiaRequests;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

// specs/04 §2 "View platform stats" (admin+, read: open to restricted and pending-deletion
// admins), specs/11 "Data exposure via page props": aggregates only, never a job payload,
// exception text or an email.

const DASHBOARD_PANELS = ['signups', 'failedJobs', 'storage', 'cocApiHealth'];

function partialDashboard(User $viewer, string $prop): TestResponse
{
    return test()->actingAs($viewer)->withHeaders([
        'X-Inertia' => 'true',
        'X-Inertia-Version' => (string) app(HandleInertiaRequests::class)->version(request()),
        'X-Inertia-Partial-Component' => 'Admin/Dashboard',
        'X-Inertia-Partial-Data' => $prop,
    ])->get('/admin');
}

it('refuses plain users the page and every panel', function (string $prop) {
    $viewer = User::factory()->create();

    $this->actingAs($viewer)->get('/admin')->assertForbidden();
    partialDashboard($viewer, $prop)->assertForbidden();
})->with(DASHBOARD_PANELS);

it('gives every panel to admins and super admins, restricted and pending-deletion ones included', function (string $role, ?string $state) {
    $factory = User::factory()->{$role}();
    $viewer = ($state === null ? $factory : $factory->{$state}())->create();

    foreach (DASHBOARD_PANELS as $prop) {
        expect(partialDashboard($viewer, $prop)->assertOk()->json("props.{$prop}"))->toBeArray();
    }
})->with([
    'admin' => ['admin', null],
    'super admin' => ['superAdmin', null],
    'restricted admin' => ['admin', 'restricted'],
    'pending-deletion admin' => ['admin', 'pendingDeletion'],
]);

it('refuses moderators, restricted ones included, the page and every panel', function (?string $state) {
    $factory = User::factory()->moderator();
    $viewer = ($state === null ? $factory : $factory->{$state}())->create();

    foreach (DASHBOARD_PANELS as $prop) {
        partialDashboard($viewer, $prop)->assertForbidden();
    }
})->with(['moderator' => [null], 'restricted moderator' => ['restricted']]);

it('sends suspended staff to the notice and signs banned staff out', function (string $prop) {
    partialDashboard(User::factory()->admin()->suspended()->create(), $prop)->assertRedirect('/account/suspended');

    partialDashboard(User::factory()->admin()->banned()->create(), $prop)->assertRedirect('/login');
    $this->assertGuest();
})->with(DASHBOARD_PANELS);

it('never puts job payloads, exception text, emails or API keys in the panels', function () {
    config(['coc.tokens' => ['sk_coc_secret_token']]);
    User::factory()->create(['email' => 'newcomer@example.com']);
    DB::table((string) config('queue.failed.table'))->insert([
        'uuid' => (string) Str::uuid(),
        'connection' => 'database',
        'queue' => 'default',
        'payload' => json_encode(['displayName' => 'App\\Jobs\\SendMail', 'data' => ['to' => 'victim@example.com']]),
        'exception' => 'RuntimeException: sk_live_private_token',
        'failed_at' => now()->subMinute(),
    ]);
    $admin = User::factory()->admin()->create();

    $props = collect(DASHBOARD_PANELS)->map(fn (string $prop) => json_encode(partialDashboard($admin, $prop)->json('props')))->implode('');

    expect($props)->toContain('App\\\\Jobs\\\\SendMail')
        ->and($props)->not->toContain('victim@example.com')
        ->and($props)->not->toContain('newcomer@example.com')
        ->and($props)->not->toContain('sk_live_private_token')
        ->and($props)->not->toContain('RuntimeException')
        ->and($props)->not->toContain('payload')
        ->and($props)->not->toContain('uuid')
        ->and($props)->toContain('keysTotal')
        ->and($props)->not->toContain('sk_coc_secret_token')
        ->and($props)->not->toContain(CocApiKey::fromToken('sk_coc_secret_token')->id);
});
