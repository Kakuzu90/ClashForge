<?php

use App\Domain\CocIntegration\Services\CocKeyPool;
use App\Http\Middleware\HandleInertiaRequests;
use App\Models\User;
use App\Support\Health\HealthChecker;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;

// specs/20 §6, NFR-OBS-6: the System Health page, admin and above (`view-platform-stats`).

beforeEach(function () {
    Date::setTestNow('2026-10-02 12:00:00');
    $this->admin = User::factory()->admin()->create();
});

function systemPanel(User $viewer, string $props): TestResponse
{
    return test()->actingAs($viewer)->withHeaders([
        'X-Inertia' => 'true',
        'X-Inertia-Version' => (string) app(HandleInertiaRequests::class)->version(request()),
        'X-Inertia-Partial-Component' => 'Admin/System',
        'X-Inertia-Partial-Data' => $props,
    ])->get('/admin/system');
}

it('renders the page with every panel deferred in its own group', function () {
    $this->actingAs($this->admin)->get('/admin/system')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/System')
            ->where('meta.title', 'System health')
            ->where('auth.can.viewPlatformStats', true)
            ->missing('queues')
            ->missing('failedJobs')
            ->missing('scheduler')
            ->missing('cocApiHealth')
            ->missing('cocKeys'));

    expect($this->actingAs($this->admin)->get('/admin/system')->viewData('page')['deferredProps'])->toBe([
        'queues' => ['queues'],
        'failedJobs' => ['failedJobs'],
        'scheduler' => ['scheduler'],
        'cocApi' => ['cocApiHealth', 'cocKeys'],
    ]);
});

it('sends every panel in the shapes the page reads', function () {
    config(['coc.tokens' => []]);
    $now = Date::now()->getTimestamp();
    DB::table((string) config('queue.connections.database.table'))->insert([
        'queue' => 'high', 'payload' => '{}', 'attempts' => 0, 'reserved_at' => null, 'available_at' => $now - 600, 'created_at' => $now - 600,
    ]);
    DB::table((string) config('queue.failed.table'))->insert([
        'uuid' => (string) Str::uuid(), 'connection' => 'database', 'queue' => 'low',
        'payload' => json_encode(['displayName' => 'App\\Jobs\\SendMail']), 'exception' => 'x', 'failed_at' => now()->subMinute(),
    ]);
    Cache::put(HealthChecker::HEARTBEAT_KEY, $now - 30);

    $props = systemPanel($this->admin, 'queues,failedJobs,scheduler,cocApiHealth,cocKeys')->assertOk()->json('props');

    expect($props['queues'][0])->toBe([
        'name' => 'high', 'waiting' => 1, 'delayed' => 0, 'reserved' => 0, 'oldestWaitSeconds' => 600,
        'maxWaitSeconds' => config('platform.health.queue_max_wait.high'), 'maxDepth' => config('platform.health.queue_max_depth'),
        'overWait' => true, 'overDepth' => false,
    ])
        ->and($props['failedJobs'])->toMatchArray(['total' => 1, 'lastHour' => 1])
        ->and($props['failedJobs']['classes'][0])->toMatchArray(['name' => 'App\\Jobs\\SendMail', 'queues' => ['low']])
        ->and($props['scheduler'])->toMatchArray(['state' => 'running', 'ageSeconds' => 30])
        ->and($props['cocApiHealth'])->toHaveKeys(['state', 'keysHealthy', 'keysTotal'])
        ->and($props['cocKeys'])->toBe([]);
});

it('lists every API key with its state', function () {
    config(['coc.tokens' => ['token-one', 'token-two']]);
    $pool = app(CocKeyPool::class);
    [$first, $second] = $pool->keys();
    $pool->markUnhealthy($second, 'accessDenied.invalidIp');

    expect(systemPanel($this->admin, 'cocKeys,cocApiHealth')->json('props'))
        ->cocKeys->toBe([
            ['id' => $first->id, 'healthy' => true, 'reason' => null, 'unhealthySince' => null],
            ['id' => $second->id, 'healthy' => false, 'reason' => 'accessDenied.invalidIp', 'unhealthySince' => now()->toIso8601String()],
        ])
        ->cocApiHealth->toMatchArray(['keysHealthy' => 1, 'keysTotal' => 2]);
});

it('stays within the query budget', function () {
    $this->actingAs($this->admin);
    DB::enableQueryLog();

    systemPanel($this->admin, 'queues,failedJobs,scheduler,cocApiHealth,cocKeys')->assertOk();

    expect(count(DB::getQueryLog()))->toBeLessThanOrEqual(25);
});

it('shows the System item in the admin nav to admins only', function () {
    $this->actingAs($this->admin)->get('/admin')->assertInertia(fn (Assert $page) => $page->where('auth.can.viewPlatformStats', true));
    $this->actingAs(User::factory()->moderator()->create())->get('/')->assertInertia(fn (Assert $page) => $page->where('auth.can.viewPlatformStats', false));
});
