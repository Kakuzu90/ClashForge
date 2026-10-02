<?php

use App\Domain\CocIntegration\Support\CocApiKey;
use App\Http\Middleware\HandleInertiaRequests;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

// specs/04 §2 "View platform stats" (admin+, a read ability: open to restricted and
// pending-deletion admins), specs/11 "Data exposure via page props": no job payload, exception
// text, email or API token ever reaches the page.

const SYSTEM_PANELS = ['queues', 'failedJobs', 'scheduler', 'cocApiHealth', 'cocKeys'];

function partialSystem(User $viewer, string $prop): TestResponse
{
    return test()->actingAs($viewer)->withHeaders([
        'X-Inertia' => 'true',
        'X-Inertia-Version' => (string) app(HandleInertiaRequests::class)->version(request()),
        'X-Inertia-Partial-Component' => 'Admin/System',
        'X-Inertia-Partial-Data' => $prop,
    ])->get('/admin/system');
}

it('sends a guest to sign in', function () {
    $this->get('/admin/system')->assertRedirect('/login');
});

it('refuses plain users and moderators the page and every panel', function (?string $role, ?string $state) {
    $factory = $role === null ? User::factory() : User::factory()->{$role}();
    $viewer = ($state === null ? $factory : $factory->{$state}())->create();

    $this->actingAs($viewer)->get('/admin/system')->assertForbidden();

    foreach (SYSTEM_PANELS as $prop) {
        partialSystem($viewer, $prop)->assertForbidden();
    }
})->with([
    'user' => [null, null],
    'moderator' => ['moderator', null],
    'restricted moderator' => ['moderator', 'restricted'],
]);

it('gives every panel to admins and super admins, restricted and pending-deletion ones included', function (string $role, ?string $state) {
    $factory = User::factory()->{$role}();
    $viewer = ($state === null ? $factory : $factory->{$state}())->create();

    foreach (SYSTEM_PANELS as $prop) {
        expect(partialSystem($viewer, $prop)->assertOk()->json("props.{$prop}"))->toBeArray();
    }
})->with([
    'admin' => ['admin', null],
    'super admin' => ['superAdmin', null],
    'restricted admin' => ['admin', 'restricted'],
    'pending-deletion admin' => ['admin', 'pendingDeletion'],
]);

it('sends suspended staff to the notice and signs banned staff out', function () {
    partialSystem(User::factory()->admin()->suspended()->create(), 'queues')->assertRedirect('/account/suspended');

    partialSystem(User::factory()->admin()->banned()->create(), 'queues')->assertRedirect('/login');
    $this->assertGuest();
});

it('never puts payloads, exception text, emails or API tokens in the panels', function () {
    config(['coc.tokens' => ['sk_coc_secret_token']]);
    $now = now()->getTimestamp();
    DB::table((string) config('queue.connections.database.table'))->insert([
        'queue' => 'low', 'payload' => json_encode(['displayName' => 'App\\Jobs\\SendMail', 'data' => ['to' => 'queued@example.com']]),
        'attempts' => 0, 'reserved_at' => null, 'available_at' => $now, 'created_at' => $now,
    ]);
    DB::table((string) config('queue.failed.table'))->insert([
        'uuid' => $uuid = (string) Str::uuid(),
        'connection' => 'database',
        'queue' => 'default',
        'payload' => json_encode(['displayName' => 'App\\Jobs\\SendMail', 'data' => ['to' => 'victim@example.com']]),
        'exception' => 'RuntimeException: sk_live_private_token',
        'failed_at' => now()->subMinute(),
    ]);
    $admin = User::factory()->admin()->create(['email' => 'chief@example.com']);

    $props = collect(SYSTEM_PANELS)->map(fn (string $prop) => json_encode(partialSystem($admin, $prop)->json("props.{$prop}")))->implode('');

    expect($props)->toContain('App\\\\Jobs\\\\SendMail')
        ->and($props)->toContain(CocApiKey::fromToken('sk_coc_secret_token')->id)
        ->and($props)->not->toContain('victim@example.com')
        ->and($props)->not->toContain('queued@example.com')
        ->and($props)->not->toContain('chief@example.com')
        ->and($props)->not->toContain('sk_live_private_token')
        ->and($props)->not->toContain('RuntimeException')
        ->and($props)->not->toContain($uuid)
        ->and($props)->not->toContain('sk_coc_secret_token');
});
