<?php

use App\Domain\CocIntegration\Data\PlayerLookupResult;
use App\Domain\CocIntegration\Data\PlayerTag;
use App\Domain\CocIntegration\Enums\CocFailureReason;
use App\Domain\CocIntegration\Enums\CocLookupStatus;
use App\Domain\CocIntegration\Enums\CocPriority;
use App\Domain\CocIntegration\Enums\SyncResourceType;
use App\Domain\CocIntegration\Enums\SyncTier;
use App\Domain\CocIntegration\Models\SyncState;
use App\Domain\CocIntegration\Services\PlayerLookup;
use App\Domain\Notifications\Enums\NotificationType;
use App\Domain\Notifications\Services\Notifier;
use App\Domain\PlayerAccounts\Enums\CocAccountStatus;
use App\Domain\PlayerAccounts\Enums\SnapshotSource;
use App\Domain\PlayerAccounts\Enums\SyncOutcome;
use App\Domain\PlayerAccounts\Events\CocAccountVerified;
use App\Domain\PlayerAccounts\Models\CocAccount;
use App\Domain\PlayerAccounts\Models\CocAccountSnapshot;
use App\Domain\PlayerAccounts\Services\AccountSyncService;
use App\Models\User;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Tests\Support\Coc\InteractsWithCoc;

// specs/09 §6, FR-COC-10: background sync of one account.

uses(InteractsWithCoc::class);

beforeEach(function () {
    Date::setTestNow('2026-10-02 12:00:00');
    $this->owner = User::factory()->create(['last_login_at' => now()->subDays(60)]);
    $this->account = CocAccount::factory()->forTag('#2PQ8GRJC')->verified()->create(['user_id' => $this->owner->id, 'th_level' => 15]);
    SyncState::factory()->forAccount($this->account->id)->create();
    $this->sync = app(AccountSyncService::class);
});

function syncState(CocAccount $account): SyncState
{
    return SyncState::query()->where('resource_type', SyncResourceType::CocAccount)->where('resource_id', $account->id)->firstOrFail();
}

/**
 * The fixture player with some changes, served by the fake from now on.
 *
 * @param  callable(array<string, mixed>): array<string, mixed>  $edit
 */
function serveFixture(callable $edit): void
{
    test()->fakeCoc()->withPlayer($edit(test()->cocFixture('players/2PQ8GRJC.json')));
}

it('stores fresh data, writes the first snapshot and schedules the next sync', function () {
    expect($this->sync->sync($this->account->id))->toBe(SyncOutcome::Changed);

    $account = $this->account->fresh();
    $snapshot = CocAccountSnapshot::query()->sole();

    expect($account)->th_level->toBe(16)->trophies->toBe(5124)->api_sync_failures->toBe(0)->status->toBe(CocAccountStatus::Verified)
        ->and($account->api_synced_at?->toIso8601String())->toBe(now()->toIso8601String())
        ->and($snapshot)->source->toBe(SnapshotSource::Scheduled)->th_level->toBe(16)->builder_hall_level->toBe(10)->trophies->toBe(5124)
        ->and($snapshot->troops)->toBe($account->troops)
        ->and(syncState($this->account))->tier->toBe(SyncTier::Cold)->consecutive_failures->toBe(0)
        ->and(syncState($this->account)->next_due_at?->toIso8601String())->toBe(now()->addSeconds((int) config('coc.sync.tiers.cold'))->toIso8601String());
});

it('writes no snapshot when nothing tracked changed, even when trophies and donations did', function () {
    $this->sync->sync($this->account->id);
    Date::setTestNow(now()->addHours(3));
    serveFixture(fn (array $p): array => [...$p, 'trophies' => 5300, 'attackWins' => 99, 'defenseWins' => 9, 'donations' => 3000]);

    expect($this->sync->sync($this->account->id))->toBe(SyncOutcome::Unchanged)
        ->and(CocAccountSnapshot::query()->count())->toBe(1)
        ->and($this->account->fresh()->trophies)->toBe(5300);
});

it('writes a snapshot when progression changed', function (callable $edit) {
    $this->sync->sync($this->account->id);
    Date::setTestNow(now()->addHours(3));
    serveFixture($edit);

    expect($this->sync->sync($this->account->id))->toBe(SyncOutcome::Changed)
        ->and(CocAccountSnapshot::query()->count())->toBe(2);
})->with([
    'town hall' => [fn (array $p): array => [...$p, 'townHallLevel' => 17]],
    'builder hall' => [fn (array $p): array => [...$p, 'builderHallLevel' => 11]],
    'best trophies' => [fn (array $p): array => [...$p, 'bestTrophies' => 6000]],
    'war stars' => [fn (array $p): array => [...$p, 'warStars' => 1481]],
    'a troop level' => [fn (array $p): array => [...$p, 'troops' => array_map(fn (array $t): array => $t['name'] === 'Barbarian' ? [...$t, 'level' => 12] : $t, $p['troops'])]],
    'a new unit' => [fn (array $p): array => [...$p, 'spells' => [...$p['spells'], ['name' => 'Brand New Spell', 'level' => 1, 'maxLevel' => 5, 'village' => 'home']]]],
    'left the clan' => [fn (array $p): array => array_diff_key($p, ['clan' => true])],
]);

it('treats a reordered unit list as no change', function () {
    $this->sync->sync($this->account->id);
    serveFixture(fn (array $p): array => [...$p, 'troops' => array_reverse($p['troops'])]);
    Date::setTestNow(now()->addHours(3));

    expect($this->sync->sync($this->account->id))->toBe(SyncOutcome::Unchanged);
});

it('syncs in the background priority, never from the cache', function () {
    $this->mock(PlayerLookup::class)->shouldReceive('find')
        ->once()
        ->withArgs(fn (PlayerTag $tag, CocPriority $priority, bool $fresh = false): bool => $tag->value === '#2PQ8GRJC' && $priority === CocPriority::Background && $fresh === false)
        ->andReturn(new PlayerLookupResult(PlayerTag::from('#2PQ8GRJC'), CocLookupStatus::Unavailable, failure: CocFailureReason::Timeout));

    expect(app(AccountSyncService::class)->sync($this->account->id))->toBe(SyncOutcome::Failed);
});

it('marks the account stale after three 404s in a row, tells the owner once, and never unverifies it', function () {
    $threshold = (int) config('coc.sync.not_found_stale');
    $notices = fn () => DB::table('notifications')->where('notifiable_id', $this->owner->id)->where('type', NotificationType::CocAccountNotFound->value)->get();

    foreach (range(1, $threshold - 1) as $_) {
        $this->fakeCoc()->notFoundNext();
        expect($this->sync->sync($this->account->id))->toBe(SyncOutcome::NotFound);
    }
    expect($notices())->toHaveCount(0)->and(syncState($this->account)->tier)->not->toBe(SyncTier::Frozen);

    $this->fakeCoc()->notFoundNext();
    $this->sync->sync($this->account->id);
    $this->fakeCoc()->notFoundNext();
    $this->sync->sync($this->account->id);

    expect($this->account->fresh())->api_sync_failures->toBe($threshold + 1)->status->toBe(CocAccountStatus::Verified)
        ->and($notices())->toHaveCount(1)
        ->and(syncState($this->account)->tier)->toBe(SyncTier::Frozen);
});

it('keeps the third 404 uncounted when its notice cannot be written, so the retry still sends it', function () {
    $threshold = (int) config('coc.sync.not_found_stale');
    $this->account->forceFill(['api_sync_failures' => $threshold - 1])->save();
    $this->mock(Notifier::class)->shouldReceive('send')->once()->andThrow(new RuntimeException('database hiccup'));
    $this->fakeCoc()->notFoundNext();

    expect(fn () => app(AccountSyncService::class)->sync($this->account->id))->toThrow(RuntimeException::class)
        ->and($this->account->fresh()->api_sync_failures)->toBe($threshold - 1);
});

it('clears the stale count once the tag is found again', function () {
    $this->account->forceFill(['api_sync_failures' => 3])->save();

    $this->sync->sync($this->account->id);

    expect($this->account->fresh()->api_sync_failures)->toBe(0)
        ->and(syncState($this->account)->tier)->toBe(SyncTier::Cold);
});

it('backs off on an API failure without touching the 404 count or writing anything', function (CocFailureReason $reason) {
    $this->account->forceFill(['api_sync_failures' => 1])->save();
    $this->fakeCoc()->failNext($reason);

    expect($this->sync->sync($this->account->id))->toBe(SyncOutcome::Failed)
        ->and($this->account->fresh())->api_sync_failures->toBe(1)->th_level->toBe(15)
        ->and(CocAccountSnapshot::query()->count())->toBe(0)
        ->and(syncState($this->account))->consecutive_failures->toBe(1)
        ->and((int) now()->diffInSeconds(syncState($this->account)->next_due_at))->toBe((int) config('coc.sync.backoff_base'));
})->with([CocFailureReason::ServerError, CocFailureReason::Timeout, CocFailureReason::Malformed]);

it('postpones without counting when the trouble is ours or the circuit is open', function (CocFailureReason $reason, ?int $retryAfter, int $wait) {
    $this->fakeCoc()->failNext($reason, $retryAfter);

    expect($this->sync->sync($this->account->id))->toBe(SyncOutcome::Postponed)
        ->and(syncState($this->account))->consecutive_failures->toBe(0)
        ->and((int) now()->diffInSeconds(syncState($this->account)->next_due_at))->toBe($wait);
})->with([
    'circuit open' => [CocFailureReason::CircuitOpen, 45, 45],
    'maintenance' => [CocFailureReason::Maintenance, 900, 900],
    'throttled, no wait given' => [CocFailureReason::Throttled, null, 300],
    'no healthy key' => [CocFailureReason::NoHealthyKey, null, 300],
]);

it('stops syncing an account that is no longer held, without calling the API', function (Closure $make) {
    $account = $make();
    SyncState::factory()->forAccount($account->id)->create();

    expect($this->sync->sync($account->id))->toBe(SyncOutcome::Skipped)
        ->and(SyncState::query()->where('resource_id', $account->id)->exists())->toBeFalse()
        ->and($this->fakeCoc()->calls())->toBe([]);
})->with([
    'released' => [fn () => CocAccount::factory()->forTag('#LQ2RJ9P0')->released()->create()],
    'unverified' => [fn () => CocAccount::factory()->forTag('#LQ2RJ9P0')->create()],
]);

it('keeps syncing a disputed account, whose holder still holds the tag', function () {
    $this->account->forceFill(['status' => CocAccountStatus::Disputed])->save();

    expect($this->sync->sync($this->account->id))->toBe(SyncOutcome::Changed)
        ->and($this->account->fresh()->status)->toBe(CocAccountStatus::Disputed);
});

it('stops a suspended account, and one that is gone', function () {
    $this->account->forceFill(['status' => CocAccountStatus::Suspended])->save();

    expect($this->sync->sync($this->account->id))->toBe(SyncOutcome::Skipped)
        ->and($this->sync->sync(999_999))->toBe(SyncOutcome::Skipped);
});

it('drops the answer when the account changed hands while the API was answering', function () {
    $this->instance(PlayerLookup::class, Mockery::mock(PlayerLookup::class, function ($mock) {
        $mock->shouldReceive('find')->andReturnUsing(function (PlayerTag $tag) {
            CocAccount::query()->whereKey($this->account->id)->update(['status' => CocAccountStatus::Released->value, 'user_id' => null]);

            return new PlayerLookupResult($tag, CocLookupStatus::Found, test()->fakeCoc()->player($tag));
        });
    }));

    expect(app(AccountSyncService::class)->sync($this->account->id))->toBe(SyncOutcome::Skipped)
        ->and(CocAccountSnapshot::query()->count())->toBe(0)
        ->and($this->account->fresh()->th_level)->toBe(15);
});

it('puts a featured account or an active owner on the hot tier, and a fading one on warm', function (bool $featured, ?int $loginDaysAgo, ?int $sessionDaysAgo, SyncTier $tier) {
    $this->owner->forceFill(['last_login_at' => $loginDaysAgo === null ? null : now()->subDays($loginDaysAgo)])->save();
    $this->account->forceFill(['is_featured' => $featured])->save();
    if ($sessionDaysAgo !== null) {
        DB::table((string) config('session.table'))->insert([
            'id' => 'session-'.$sessionDaysAgo, 'user_id' => $this->owner->id,
            'payload' => '', 'last_activity' => now()->subDays($sessionDaysAgo)->getTimestamp(),
        ]);
    }

    $this->sync->sync($this->account->id);

    expect(syncState($this->account)->tier)->toBe($tier);
})->with([
    'featured' => [true, null, null, SyncTier::Hot],
    'signed in 3 days ago' => [false, 3, null, SyncTier::Hot],
    'signed in exactly 7 days ago' => [false, 7, null, SyncTier::Hot],
    'signed in 20 days ago' => [false, 20, null, SyncTier::Warm],
    'remembered session busy yesterday' => [false, 60, 1, SyncTier::Hot],
    'signed in 31 days ago' => [false, 31, null, SyncTier::Cold],
    'never active' => [false, null, null, SyncTier::Cold],
]);

it('starts the schedule with a verification snapshot when an account is verified', function () {
    $user = User::factory()->create(['last_login_at' => now()]);
    $account = CocAccount::factory()->forTag('#YC8V2QG9')->verified()->create(['user_id' => $user->id, 'api_sync_failures' => 4]);

    CocAccountVerified::dispatch($account->id, $user->id, 1);

    expect(CocAccountSnapshot::query()->where('coc_account_id', $account->id)->sole()->source)->toBe(SnapshotSource::Verification)
        ->and($account->fresh()->api_sync_failures)->toBe(0)
        ->and(syncState($account))->tier->toBe(SyncTier::Hot)->consecutive_failures->toBe(0)
        ->and(syncState($account)->next_due_at?->toIso8601String())->toBe(now()->addSeconds((int) config('coc.sync.tiers.hot'))->toIso8601String());

    // A second verification in the same second keeps one snapshot.
    CocAccountVerified::dispatch($account->id, $user->id, 2);
    expect(CocAccountSnapshot::query()->where('coc_account_id', $account->id)->count())->toBe(1);
});
