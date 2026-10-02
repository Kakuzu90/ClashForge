<?php

use App\Domain\CocIntegration\Data\PlayerTag;
use App\Domain\CocIntegration\Enums\CocFailureReason;
use App\Domain\PlayerAccounts\Enums\AttachOutcome;
use App\Domain\PlayerAccounts\Enums\ClaimFailureReason;
use App\Domain\PlayerAccounts\Enums\ClaimStatus;
use App\Domain\PlayerAccounts\Enums\CocAccountStatus;
use App\Domain\PlayerAccounts\Events\CocAccountAttached;
use App\Domain\PlayerAccounts\Models\CocAccount;
use App\Domain\PlayerAccounts\Models\CocAccountClaim;
use App\Domain\PlayerAccounts\Services\AttachAccountService;
use App\Models\User;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Tests\Support\Auth\CapturesSecurityLog;
use Tests\Support\Coc\InteractsWithCoc;

// specs/13 §3 steps 1–5 and §4; FR-COC-1, FR-COC-3, FR-COC-6.

uses(InteractsWithCoc::class, CapturesSecurityLog::class);

beforeEach(function () {
    Date::setTestNow('2026-10-02 12:00:00');
    $this->captureSecurityLog();
    $this->user = User::factory()->create();
    $this->tag = PlayerTag::from('#2PQ8GRJC');
    $this->service = app(AttachAccountService::class);
});

it('previews the player without writing anything (specs/09 §9 step 1)', function () {
    $result = $this->service->preview($this->user, $this->tag);

    expect($result->outcome)->toBe(AttachOutcome::Ready)
        ->and($result->player?->name)->toBe('Fixture Chief')
        ->and($result->player?->townHallLevel)->toBe(16)
        ->and($result->player?->trophies)->toBe(5124)
        ->and($result->player?->clanName)->toBe('Fixture Clan')
        ->and(CocAccount::query()->count())->toBe(0)
        ->and(CocAccountClaim::query()->count())->toBe(0);
});

it('attaches an unverified account with the player data (FR-COC-3)', function () {
    Event::fake([CocAccountAttached::class]);

    $result = $this->service->attach($this->user, $this->tag);

    $account = CocAccount::query()->sole();
    // jsonb does not keep object key order, so JSON columns compare with toEqual.
    expect($result->outcome)->toBe(AttachOutcome::Attached)
        ->and($result->accountUlid)->toBe($account->ulid)
        ->and($account->user_id)->toBe($this->user->id)
        ->and($account->status)->toBe(CocAccountStatus::Unverified)
        ->and($account->tag)->toBe('#2PQ8GRJC')
        ->and($account->tag_normalized)->toBe('2PQ8GRJC')
        ->and($account->ign)->toBe('Fixture Chief')
        ->and($account->th_level)->toBe(16)
        ->and($account->builder_hall_level)->toBe(10)
        ->and($account->xp_level)->toBe(231)
        ->and($account->trophies)->toBe(5124)
        ->and($account->best_trophies)->toBe(5602)
        ->and($account->builder_trophies)->toBe(4210)
        ->and($account->war_stars)->toBe(1480)
        ->and($account->attack_wins)->toBe(84)
        ->and($account->defense_wins)->toBe(6)
        ->and($account->donations)->toBe(2210)
        ->and($account->donations_received)->toBe(1650)
        ->and($account->clan_tag)->toBe('#2Q8URJ9L')
        ->and($account->clan_role)->toBe('coLeader')
        ->and($account->league_name)->toBe('Legend League')
        ->and($account->league_icon_url)->toContain('/leagues/288/')
        ->and($account->troops)->toHaveCount(4)
        ->and($account->troops[2])->toEqual(['name' => 'Super Barbarian', 'level' => 11, 'max_level' => 12, 'village' => 'home', 'super_troop_active' => true])
        ->and($account->heroes)->toHaveCount(2)
        ->and($account->spells)->toHaveCount(2)
        ->and($account->hero_equipment)->toHaveCount(2)
        ->and($account->labels)->toEqual([['id' => 57000000, 'name' => 'Clan Wars']])
        ->and($account->raw_payload)->toEqual($this->cocFixture('players/2PQ8GRJC.json'))
        ->and($account->api_synced_at?->equalTo(Date::now()))->toBeTrue()
        ->and($account->is_featured)->toBeFalse();

    $claim = CocAccountClaim::query()->sole();
    expect($claim->status)->toBe(ClaimStatus::Pending)
        ->and($claim->coc_account_id)->toBe($account->id)
        ->and($claim->user_id)->toBe($this->user->id);

    Event::assertDispatched(CocAccountAttached::class, fn ($e) => $e->accountId === $account->id && $e->userId === $this->user->id);
    expect(collect($this->securityEvents())->pluck('message'))->toContain('coc.attach_attempt');
});

it('says when the tag is already attached to this user (specs/13 §3 step 3)', function () {
    $own = CocAccount::factory()->for($this->user)->forTag('#2PQ8GRJC')->create();

    expect($this->service->preview($this->user, $this->tag)->outcome)->toBe(AttachOutcome::AlreadyAttached)
        ->and($this->service->attach($this->user, $this->tag)->accountUlid)->toBe($own->ulid)
        ->and(CocAccount::query()->count())->toBe(1);
    expect($this->fakeCoc()->calls())->toBe([]);
});

it('trusts the API on an unknown tag (specs/23 §2)', function () {
    $tag = PlayerTag::from('#PYLQ');

    expect($this->service->preview($this->user, $tag)->outcome)->toBe(AttachOutcome::NotFound)
        ->and($this->service->attach($this->user, $tag)->outcome)->toBe(AttachOutcome::NotFound)
        ->and(CocAccount::query()->count())->toBe(0)
        ->and(CocAccountClaim::query()->sole()->failure_reason)->toBe(ClaimFailureReason::NotFound);
});

it('refuses a tag another user holds and names them (specs/13 §4, FR-COC-6)', function (string $state) {
    $holder = User::factory()->create(['username' => 'player123']);
    CocAccount::factory()->for($holder)->forTag('#2PQ8GRJC')->{$state}()->create();

    $preview = $this->service->preview($this->user, $this->tag);
    $attach = $this->service->attach($this->user, $this->tag);

    expect($preview->outcome)->toBe(AttachOutcome::VerifiedElsewhere)
        ->and($preview->holderUsername)->toBe('player123')
        ->and($preview->player?->name)->toBe('Fixture Chief')
        ->and($attach->outcome)->toBe(AttachOutcome::VerifiedElsewhere)
        ->and(CocAccount::query()->where('user_id', $this->user->id)->exists())->toBeFalse()
        ->and(CocAccountClaim::query()->sole()->failure_reason)->toBe(ClaimFailureReason::AlreadyClaimed);
})->with(['verified', 'disputed']);

it('lets unverified claims coexist (specs/13 §4)', function () {
    CocAccount::factory()->forTag('#2PQ8GRJC')->create();

    expect($this->service->attach($this->user, $this->tag)->outcome)->toBe(AttachOutcome::Attached)
        ->and(CocAccount::query()->where('tag_normalized', '2PQ8GRJC')->count())->toBe(2);
});

it('reuses a released row for the tag, keeping its history (specs/13 §6)', function () {
    $released = CocAccount::factory()->forTag('#2PQ8GRJC')->released()->create();

    $result = $this->service->attach($this->user, $this->tag);

    expect($result->accountUlid)->toBe($released->ulid)
        ->and($released->refresh()->user_id)->toBe($this->user->id)
        ->and($released->status)->toBe(CocAccountStatus::Unverified)
        ->and(CocAccount::query()->count())->toBe(1);
});

it('reports the API as unavailable and writes nothing but the failed claim', function () {
    $this->fakeCoc()->failNext(CocFailureReason::Maintenance, 120);

    $result = $this->service->attach($this->user, $this->tag);

    expect($result->outcome)->toBe(AttachOutcome::Unavailable)
        ->and($result->retryAfter)->toBe(120)
        ->and(CocAccount::query()->count())->toBe(0)
        ->and(CocAccountClaim::query()->sole()->failure_reason)->toBe(ClaimFailureReason::ApiError);
});

it('attaches from the last good answer while the API is down (specs/13 §9)', function () {
    $this->useHttpCoc();
    Http::fakeSequence('*')->push($this->cocFixtureBody('players/2PQ8GRJC.json'))->push('', 503);

    // Resolved after the driver switch, so it holds the http client.
    $service = app(AttachAccountService::class);
    $service->preview($this->user, $this->tag);
    $this->travel((int) config('coc.cache.player_ttl') + 1)->seconds();
    $result = $service->attach($this->user, $this->tag);

    expect($result->outcome)->toBe(AttachOutcome::Attached)
        ->and($result->player?->stale)->toBeTrue()
        ->and(CocAccount::query()->sole()->api_synced_at?->equalTo(Date::now()->subSeconds((int) config('coc.cache.player_ttl') + 1)))->toBeTrue();
});

it('counts each distinct tag once an hour against coc-attach (specs/04 §4)', function () {
    $limit = (int) config('coc.accounts.attach_per_hour');
    $tags = ['#2PQ8GRJC', '#LQ2RJ9P0', '#YC8V2QG9', '#GRJ0P8UV', '#PYLQ', '#PYLR', '#PYLG'];

    foreach (array_slice($tags, 0, $limit) as $tag) {
        $this->service->preview($this->user, PlayerTag::from($tag));
        $this->service->preview($this->user, PlayerTag::from($tag));
    }
    $this->service->attach($this->user, PlayerTag::from($tags[0]));

    $over = $this->service->preview($this->user, PlayerTag::from($tags[$limit]));
    expect($over->outcome)->toBe(AttachOutcome::RateLimited)
        ->and($over->retryAfter)->toBeGreaterThan(0)
        ->and(collect($this->securityEvents())->where('context.outcome', 'rate_limited'))->toHaveCount(1);

    $this->travel(3601)->seconds();
    expect($this->service->preview($this->user, PlayerTag::from($tags[$limit]))->outcome)->not->toBe(AttachOutcome::RateLimited);
});

it('flags a user at or above the anomaly threshold, once per reflag window (specs/23 §2)', function () {
    config(['coc.accounts.anomaly_accounts' => 2, 'coc.accounts.attach_per_hour' => 10]);
    CocAccount::factory()->count(2)->for($this->user)->create();

    $this->service->attach($this->user, PlayerTag::from('#2PQ8GRJC'));
    $this->service->attach($this->user, PlayerTag::from('#LQ2RJ9P0'));
    expect(collect($this->securityEvents())->where('message', 'coc.attach_anomaly'))->toHaveCount(1);

    $this->travel((int) config('coc.accounts.anomaly_reflag_hours'))->hours();
    $this->service->attach($this->user, PlayerTag::from('#YC8V2QG9'));
    expect(collect($this->securityEvents())->where('message', 'coc.attach_anomaly'))->toHaveCount(2);
});

it('reports the API as unavailable in the preview too', function () {
    $this->fakeCoc()->failNext(CocFailureReason::Timeout);

    $result = $this->service->preview($this->user, $this->tag);

    expect($result->outcome)->toBe(AttachOutcome::Unavailable)
        ->and($result->player)->toBeNull()
        ->and(CocAccountClaim::query()->count())->toBe(0);
});

it('writes a rate_limited claim row when an attach is over the limit', function () {
    config(['coc.accounts.attach_per_hour' => 1]);
    $this->service->preview($this->user, PlayerTag::from('#LQ2RJ9P0'));

    $result = $this->service->attach($this->user, $this->tag);

    expect($result->outcome)->toBe(AttachOutcome::RateLimited)
        ->and(CocAccount::query()->count())->toBe(0)
        ->and(CocAccountClaim::query()->sole()->failure_reason)->toBe(ClaimFailureReason::RateLimited);
});

it('does not count a refused tag as attempted, so it is not free next time', function () {
    config(['coc.accounts.attach_per_hour' => 1]);
    $this->service->preview($this->user, PlayerTag::from('#LQ2RJ9P0'));

    expect($this->service->preview($this->user, $this->tag)->outcome)->toBe(AttachOutcome::RateLimited)
        ->and($this->service->preview($this->user, $this->tag)->outcome)->toBe(AttachOutcome::RateLimited);
});
