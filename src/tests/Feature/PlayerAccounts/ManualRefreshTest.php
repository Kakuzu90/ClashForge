<?php

use App\Domain\CocIntegration\Data\PlayerLookupResult;
use App\Domain\CocIntegration\Data\PlayerTag;
use App\Domain\CocIntegration\Enums\CocFailureReason;
use App\Domain\CocIntegration\Enums\CocLookupStatus;
use App\Domain\CocIntegration\Enums\SyncResourceType;
use App\Domain\CocIntegration\Enums\SyncTier;
use App\Domain\CocIntegration\Models\SyncState;
use App\Domain\CocIntegration\Services\PlayerLookup;
use App\Domain\PlayerAccounts\Enums\CocAccountStatus;
use App\Domain\PlayerAccounts\Enums\SnapshotSource;
use App\Domain\PlayerAccounts\Enums\SyncOutcome;
use App\Domain\PlayerAccounts\Jobs\SyncCocAccountJob;
use App\Domain\PlayerAccounts\Models\CocAccount;
use App\Domain\PlayerAccounts\Models\CocAccountSnapshot;
use App\Domain\PlayerAccounts\Services\AccountSyncService;
use App\Domain\PlayerAccounts\Support\RefreshCooldown;
use App\Domain\PlayerAccounts\Support\SyncTierRules;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\Coc\InteractsWithCoc;

// P2-20: manual refresh (FR-COC-9, specs/09 §6) and the "viewed in the last 24 h" tier input.

uses(InteractsWithCoc::class);

beforeEach(function () {
    Date::setTestNow('2026-10-06 12:00:00');
    $this->owner = User::factory()->create(['last_login_at' => now()->subDays(60)]);
    $this->account = CocAccount::factory()->for($this->owner)->forTag('#2PQ8GRJC')->verified()->create(['th_level' => 15]);
    SyncState::factory()->forAccount($this->account->id)->create(['last_success_at' => now()->subDay(), 'next_due_at' => now()->addDays(2)]);
});

function refreshAccount(CocAccount $account, ?User $as = null): TestResponse
{
    return test()->actingAs($as ?? $account->user)->from("/accounts/{$account->ulid}")->post("/accounts/{$account->ulid}/refresh");
}

function accountSyncState(CocAccount $account): ?SyncState
{
    return SyncState::query()->where('resource_type', SyncResourceType::CocAccount)->where('resource_id', $account->id)->first();
}

it('refreshes at once: fresh data, a manual snapshot, the schedule restarted', function () {
    refreshAccount($this->account)->assertRedirect("/accounts/{$this->account->ulid}")->assertSessionHas('success', 'Game data updated.');

    $account = $this->account->fresh();
    expect($account->th_level)->toBe(16)
        ->and($account->api_synced_at?->toIso8601String())->toBe(now()->toIso8601String())
        ->and(CocAccountSnapshot::query()->sole()->source)->toBe(SnapshotSource::Manual)
        ->and($this->fakeCoc()->calls())->toBe([['endpoint' => 'players', 'tag' => '#2PQ8GRJC']])
        ->and(accountSyncState($this->account)->last_success_at?->toIso8601String())->toBe(now()->toIso8601String())
        ->and(accountSyncState($this->account)->next_due_at?->toIso8601String())->toBe(now()->addSeconds(SyncTier::Cold->intervalSeconds())->toIso8601String());
});

it('allows one refresh per account per cooldown, and says how long is left', function () {
    $cooldown = (int) config('coc.sync.manual_cooldown');
    refreshAccount($this->account)->assertSessionHas('success');

    Date::setTestNow(now()->addSeconds($cooldown - 150));
    refreshAccount($this->account)->assertSessionHas('error', 'This account was refreshed recently. You can refresh again in 3 minutes.');
    expect($this->fakeCoc()->calls())->toHaveCount(1);

    $this->actingAs($this->owner)->get("/accounts/{$this->account->ulid}")
        ->assertInertia(fn (Assert $page) => $page->where('account.canRefresh', true)->where('account.refreshWaitSeconds', 150));

    Date::setTestNow(now()->addSeconds(150));
    refreshAccount($this->account)->assertSessionHas('success', 'Game data updated.');
    expect($this->fakeCoc()->calls())->toHaveCount(2)
        ->and(CocAccountSnapshot::query()->count())->toBe(1);
});

it('hands over to the job when the API is slower than the time limit', function () {
    Queue::fake();
    $this->fakeCoc()->failNext(CocFailureReason::Deadline);

    refreshAccount($this->account)->assertSessionHas('success', 'Clash of Clans is slow to answer, so the update will finish in the background. Reload the page in a minute.');

    Queue::assertPushed(SyncCocAccountJob::class, fn (SyncCocAccountJob $job) => $job->accountId === $this->account->id && $job->source === SnapshotSource::Manual);
    expect($this->account->fresh()->th_level)->toBe(15)
        ->and(accountSyncState($this->account)->consecutive_failures)->toBe(0);
    refreshAccount($this->account)->assertSessionHas('error');
});

it("writes the job's snapshot as manual", function () {
    (new SyncCocAccountJob($this->account->id, SnapshotSource::Manual))->handle(app(AccountSyncService::class));

    expect(CocAccountSnapshot::query()->sole()->source)->toBe(SnapshotSource::Manual)
        ->and($this->account->fresh()->th_level)->toBe(16);
});

it('changes nothing and gives the cooldown back when the API cannot be asked', function (CocFailureReason $reason) {
    $this->fakeCoc()->failNext($reason);

    refreshAccount($this->account)->assertSessionHas('error', 'The game API is unavailable right now, so nothing was updated. Try again in a few minutes.');

    expect($this->account->fresh()->th_level)->toBe(15)
        ->and(CocAccountSnapshot::query()->count())->toBe(0)
        ->and(accountSyncState($this->account)->consecutive_failures)->toBe(0)
        ->and(accountSyncState($this->account)->next_due_at?->toIso8601String())->toBe(now()->addDays(2)->toIso8601String());
    refreshAccount($this->account)->assertSessionHas('success', 'Game data updated.');
})->with([
    'circuit open' => [CocFailureReason::CircuitOpen],
    'maintenance' => [CocFailureReason::Maintenance],
    'out of budget' => [CocFailureReason::Throttled],
    'no healthy key' => [CocFailureReason::NoHealthyKey],
    'server error' => [CocFailureReason::ServerError],
    'malformed answer (specs/23 §5)' => [CocFailureReason::Malformed],
]);

it('counts a 404 like a sync does, and keeps the account verified', function () {
    $this->fakeCoc()->notFoundNext();

    refreshAccount($this->account)->assertSessionHas('error', "Clash of Clans can't find this tag right now. It may have been renamed or deleted in game.");

    expect($this->account->fresh())->api_sync_failures->toBe(1)->status->toBe(CocAccountStatus::Verified)
        ->and(accountSyncState($this->account)->consecutive_failures)->toBe(1);
});

it('refreshes an unverified row by hand: data only, no snapshot, no schedule (specs/09 §6)', function () {
    $unverified = CocAccount::factory()->for($this->owner)->forTag('#9VQ0YRJ8')->create(['th_level' => 12]);
    $this->fakeCoc()->withPlayer([...$this->cocFixture('players/2PQ8GRJC.json'), 'tag' => '#9VQ0YRJ8', 'townHallLevel' => 13]);

    refreshAccount($unverified)->assertSessionHas('success', 'Game data updated.');

    expect($unverified->fresh())->th_level->toBe(13)->status->toBe(CocAccountStatus::Unverified)
        ->and(CocAccountSnapshot::query()->count())->toBe(0)
        ->and(accountSyncState($unverified))->toBeNull();

    $this->fakeCoc()->notFoundNext();
    Date::setTestNow(now()->addSeconds((int) config('coc.sync.manual_cooldown')));
    refreshAccount($unverified)->assertSessionHas('error');
    expect($unverified->fresh()->api_sync_failures)->toBe(0)
        ->and(accountSyncState($unverified))->toBeNull();
});

it('never gives an unverified row a schedule from the job, even when it fails', function () {
    $unverified = CocAccount::factory()->forTag('#2PQ8GRJC')->create();
    SyncState::query()->delete();
    $this->fakeCoc()->failNext(CocFailureReason::ServerError);

    $job = new SyncCocAccountJob($unverified->id, SnapshotSource::Manual);
    $job->handle(app(AccountSyncService::class));
    $job->failed(new RuntimeException('boom'));

    expect(SyncState::query()->count())->toBe(0);
    expect(app(AccountSyncService::class)->sync($unverified->id, SnapshotSource::Manual))->toBe(SyncOutcome::Changed)
        ->and(SyncState::query()->count())->toBe(0)
        ->and(app(AccountSyncService::class)->sync($unverified->id))->toBe(SyncOutcome::Skipped);
});

it('lets the holder of a disputed account and a restricted owner refresh', function () {
    $restricted = User::factory()->restricted()->create();
    $mine = CocAccount::factory()->for($restricted)->forTag('#2PQ8GRJC')->create();
    $this->account->forceFill(['status' => CocAccountStatus::Disputed])->save();

    refreshAccount($this->account)->assertSessionHas('success');
    refreshAccount($mine)->assertSessionHas('success');
});

it('refuses a suspended row, which stays with staff', function () {
    $this->account->forceFill(['status' => CocAccountStatus::Suspended])->save();

    refreshAccount($this->account)->assertForbidden();
    expect($this->fakeCoc()->calls())->toBe([]);
});

it('shows the button to the owner only', function () {
    $this->actingAs(User::factory()->create())->get("/accounts/{$this->account->ulid}")
        ->assertInertia(fn (Assert $page) => $page->component('Accounts/Show')->where('account.canRefresh', false)->where('account.refreshWaitSeconds', 0));
    $this->get("/accounts/{$this->account->ulid}")
        ->assertInertia(fn (Assert $page) => $page->where('account.canRefresh', false));
    $this->actingAs($this->owner)->get("/accounts/{$this->account->ulid}")
        ->assertInertia(fn (Assert $page) => $page->where('account.canRefresh', true)->where('account.refreshWaitSeconds', 0));
});

it('records signed-in views at most hourly, without touching updated_at, and never guest views', function () {
    $updated = $this->account->updated_at;
    $this->get("/accounts/{$this->account->ulid}")->assertOk();
    expect($this->account->fresh()->last_viewed_at)->toBeNull();

    Date::setTestNow(now()->addMinute());
    $this->actingAs(User::factory()->create())->get("/accounts/{$this->account->ulid}")->assertOk();
    $viewed = now()->toIso8601String();

    Date::setTestNow(now()->addMinutes(30));
    $this->actingAs($this->owner)->get("/accounts/{$this->account->ulid}")->assertOk();
    expect($this->account->fresh()->last_viewed_at?->toIso8601String())->toBe($viewed)
        ->and($this->account->fresh()->updated_at?->toIso8601String())->toBe($updated->toIso8601String());

    Date::setTestNow(now()->addSeconds((int) config('coc.sync.view_record_seconds')));
    $this->actingAs($this->owner)->get("/accounts/{$this->account->ulid}")->assertOk();
    expect($this->account->fresh()->last_viewed_at?->toIso8601String())->toBe(now()->toIso8601String());
});

it('moves a viewed account up to the hot tier at once', function () {
    $this->actingAs(User::factory()->create())->get("/accounts/{$this->account->ulid}");

    $state = accountSyncState($this->account);
    expect($state->tier)->toBe(SyncTier::Hot)
        // Last synced a day ago, so it is due now rather than in two days.
        ->and($state->next_due_at?->toIso8601String())->toBe(now()->toIso8601String());
});

it('promotes without ever pushing the next sync later, and leaves frozen and stopped rows alone', function () {
    $state = accountSyncState($this->account);
    $state->forceFill(['last_success_at' => now()->subMinutes(30), 'next_due_at' => now()->addHours(10), 'tier' => SyncTier::Warm])->save();
    $this->actingAs($this->owner)->get("/accounts/{$this->account->ulid}");
    expect(accountSyncState($this->account)->next_due_at?->toIso8601String())->toBe(now()->addMinutes(90)->toIso8601String());

    foreach ([['tier' => SyncTier::Frozen, 'next_due_at' => now()->addDays(7)], ['tier' => SyncTier::Frozen, 'next_due_at' => null]] as $i => $frozen) {
        $other = CocAccount::factory()->verified()->create();
        SyncState::factory()->forAccount($other->id)->create($frozen);
        $this->actingAs($this->owner)->get("/accounts/{$other->ulid}");

        expect(accountSyncState($other))->tier->toBe(SyncTier::Frozen)
            ->and(accountSyncState($other)->next_due_at?->toIso8601String())->toBe($frozen['next_due_at']?->toIso8601String());
    }
});

it('keeps a viewed account hot at its next sync, for 24 hours', function () {
    $viewed = CarbonImmutable::now();

    expect(SyncTierRules::for(false, null, $viewed))->toBe(SyncTier::Hot)
        ->and(SyncTierRules::for(false, null, $viewed->subHours((int) config('coc.sync.hot_viewed_hours'))->subMinute()))->toBe(SyncTier::Cold);

    $this->account->forceFill(['last_viewed_at' => now()->subHours(2)])->save();
    app(AccountSyncService::class)->sync($this->account->id);
    expect(accountSyncState($this->account)->tier)->toBe(SyncTier::Hot);
});

it('caps refreshes per user across all their accounts, and gives the account its cooldown back', function () {
    config(['coc.sync.manual_per_hour' => 2]);
    $accounts = collect(['#9VQ0YRJ8', '#8QU2PLGR', '#2Q8URJ9L'])->map(fn (string $tag) => CocAccount::factory()->for($this->owner)->forTag($tag)->create());

    refreshAccount($accounts[0])->assertSessionHas('error');
    refreshAccount($accounts[1])->assertSessionHas('error');
    refreshAccount($accounts[2])->assertSessionHas('error', 'You have refreshed a lot of accounts in the last hour. You can refresh again in 60 minutes.');

    expect($this->fakeCoc()->calls())->toHaveCount(2);
    $this->actingAs($this->owner)->get("/accounts/{$accounts[2]->ulid}")
        ->assertInertia(fn (Assert $page) => $page->where('account.refreshWaitSeconds', 0));
});

it('gives both limits back when nothing was stored, so an outage never uses up the hour', function () {
    config(['coc.sync.manual_per_hour' => 1]);
    $this->fakeCoc()->failNext(CocFailureReason::ServerError)->failNext(CocFailureReason::ServerError);

    refreshAccount($this->account)->assertSessionHas('error');
    refreshAccount($this->account)->assertSessionHas('error');
    refreshAccount($this->account)->assertSessionHas('success', 'Game data updated.');
});

it('gives the refresh back when something breaks on our side', function () {
    $this->mock(PlayerLookup::class)->shouldReceive('find')->andThrow(new RuntimeException('database hiccup'));
    $this->withoutExceptionHandling();

    expect(fn () => refreshAccount($this->account))->toThrow(RuntimeException::class);
    expect(RefreshCooldown::wait($this->owner->id, $this->account->id))->toBe(0);
});

it('stores nothing from an answer it cannot read, and gives the refresh back (specs/23 §5)', function () {
    $this->mock(PlayerLookup::class)->shouldReceive('find')
        ->andReturn(new PlayerLookupResult(PlayerTag::from('#2PQ8GRJC'), CocLookupStatus::Found));

    refreshAccount($this->account)->assertSessionHas('error', 'The game API is unavailable right now, so nothing was updated. Try again in a few minutes.');

    expect($this->account->fresh()->th_level)->toBe(15)
        ->and(CocAccountSnapshot::query()->count())->toBe(0)
        ->and(RefreshCooldown::wait($this->owner->id, $this->account->id))->toBe(0);
});

it('writes one snapshot when a refresh lands right after a scheduled sync', function () {
    (new SyncCocAccountJob($this->account->id))->handle(app(AccountSyncService::class));
    refreshAccount($this->account)->assertSessionHas('success', 'Game data updated.');

    expect(CocAccountSnapshot::query()->sole()->source)->toBe(SnapshotSource::Scheduled);
});

it("hands an unverified row's slow refresh to the job too", function () {
    Queue::fake();
    $unverified = CocAccount::factory()->for($this->owner)->forTag('#9VQ0YRJ8')->create();
    $this->fakeCoc()->failNext(CocFailureReason::Deadline);

    refreshAccount($unverified)->assertSessionHas('success');

    Queue::assertPushed(SyncCocAccountJob::class, fn (SyncCocAccountJob $job) => $job->accountId === $unverified->id && $job->source === SnapshotSource::Manual);
});

it('loads a sync job queued before the source existed as a scheduled one', function () {
    $job = (new ReflectionClass(SyncCocAccountJob::class))->newInstanceWithoutConstructor();

    expect($job->source)->toBe(SnapshotSource::Scheduled);
});

it('never pulls forward an account that is backing off or already claimed', function (Closure $state) {
    accountSyncState($this->account)->forceFill($state())->save();
    $before = accountSyncState($this->account)->next_due_at?->toIso8601String();

    $this->actingAs($this->owner)->get("/accounts/{$this->account->ulid}");

    expect(accountSyncState($this->account)->tier)->toBe(SyncTier::Cold)
        ->and(accountSyncState($this->account)->next_due_at?->toIso8601String())->toBe($before);
})->with([
    'backing off' => [fn () => ['consecutive_failures' => 2, 'next_due_at' => now()->addHours(5)]],
    'claimed, job queued' => [fn () => ['next_due_at' => now()->addSeconds((int) config('coc.sync.claim_seconds') - 60)]],
]);

it('reads its limits from config', function () {
    expect(config('coc.sync'))->toMatchArray([
        'manual_timeout' => 3,
        'manual_cooldown' => 600,
        'hot_viewed_hours' => 24,
        'view_record_seconds' => 3600,
        'views_per_viewer_per_hour' => 30,
        'manual_per_hour' => 20,
    ]);
});
