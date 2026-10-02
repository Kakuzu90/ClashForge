<?php

use App\Domain\CocIntegration\Models\CocApiRequest;
use App\Domain\CocIntegration\Support\CocCacheKeys;
use App\Http\Middleware\HandleInertiaRequests;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Date;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;

// specs/09 §7 and specs/23 §5: a site banner while the API is unavailable. FR-ADMIN-5: the
// dashboard's Clash of Clans API panel.

beforeEach(function () {
    Date::setTestNow('2026-10-02 12:00:00');
});

function openCircuit(string $reason, int $secondsLeft): void
{
    $now = Date::now()->getTimestamp();
    Cache::put(CocCacheKeys::circuit(), ['reason' => $reason, 'until' => $now + $secondsLeft, 'opened_at' => $now - 30], 3600);
}

function apiPanel(User $viewer): TestResponse
{
    return test()->actingAs($viewer)->withHeaders([
        'X-Inertia' => 'true',
        'X-Inertia-Version' => (string) app(HandleInertiaRequests::class)->version(request()),
        'X-Inertia-Partial-Component' => 'Admin/Dashboard',
        'X-Inertia-Partial-Data' => 'cocApiHealth',
    ])->get('/admin');
}

it('shares the banner only while the breaker is open', function (?string $reason, int $secondsLeft, ?array $expected) {
    if ($reason !== null) {
        openCircuit($reason, $secondsLeft);
    }

    $this->get('/')->assertInertia(fn (Assert $page) => $page->where('cocApi', $expected));
    $this->actingAs(User::factory()->create())->get('/notifications')->assertInertia(fn (Assert $page) => $page->where('cocApi', $expected));
})->with([
    'closed' => [null, 0, null],
    'open after failures' => ['failures', 60, ['reason' => 'failures']],
    'open for maintenance' => ['maintenance', 900, ['reason' => 'maintenance']],
    'half-open, the next call probes' => ['failures', -1, null],
]);

it('puts no time, key or error detail in the banner prop', function () {
    openCircuit('maintenance', 900);

    $this->get('/')->assertInertia(fn (Assert $page) => $page->where('cocApi', fn ($notice) => array_keys($notice->toArray()) === ['reason']));
});

it('sums the last day of calls for the panel', function () {
    CocApiRequest::factory()->count(6)->create(['created_at' => now()->subHours(2)]);
    CocApiRequest::factory()->count(3)->cached()->create(['created_at' => now()->subHours(2)]);
    CocApiRequest::factory()->create(['status_code' => 404, 'error_code' => 'notFound', 'created_at' => now()->subHour()]);
    CocApiRequest::factory()->count(2)->create(['status_code' => 503, 'error_code' => 'inMaintenance', 'created_at' => now()->subHour()]);
    CocApiRequest::factory()->create(['status_code' => null, 'error_code' => 'timeout', 'created_at' => now()->subHour()]);
    // The key pool's and the budget's answers, which the breaker does not count (specs/09 §7).
    CocApiRequest::factory()->create(['status_code' => 429, 'error_code' => 'requestThrottled', 'created_at' => now()->subHour()]);
    CocApiRequest::factory()->create(['status_code' => 403, 'error_code' => 'accessDenied', 'created_at' => now()->subHour()]);
    CocApiRequest::factory()->count(5)->create(['status_code' => 500, 'error_code' => 'server_error', 'created_at' => now()->subHours(25)]);
    openCircuit('maintenance', 600);

    $panel = apiPanel(User::factory()->admin()->create())->assertOk()->json('props.cocApiHealth');

    expect($panel)->toMatchArray([
        'state' => 'open', 'reason' => 'maintenance', 'windowHours' => 24, 'calls' => 12, 'cacheHits' => 3,
        'failures' => 3, 'failureRate' => 0.25, 'topError' => 'inMaintenance',
    ])->and($panel['openUntil'])->toStartWith('2026-10-02T12:10:00')
        ->and($panel['keysTotal'])->toBeGreaterThanOrEqual($panel['keysHealthy']);
});

it('reports an empty window and a closed breaker', function () {
    expect(apiPanel(User::factory()->admin()->create())->json('props.cocApiHealth'))
        ->toMatchArray(['state' => 'closed', 'reason' => null, 'calls' => 0, 'failureRate' => null, 'topError' => null]);
});

it('reads its window from config', function () {
    expect(config('coc.health.window_hours'))->toBe(24);
    config(['coc.health.window_hours' => 1]);
    CocApiRequest::factory()->create(['created_at' => now()->subHours(2)]);

    expect(apiPanel(User::factory()->admin()->create())->json('props.cocApiHealth'))->toMatchArray(['calls' => 0, 'windowHours' => 1]);
});

it('leaves the panel out for anyone below admin', function () {
    apiPanel(User::factory()->moderator()->create())->assertForbidden();
    apiPanel(User::factory()->create())->assertForbidden();
});
