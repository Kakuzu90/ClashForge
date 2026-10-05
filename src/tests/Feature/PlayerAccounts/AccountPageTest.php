<?php

use App\Domain\Clans\Models\Clan;
use App\Domain\PlayerAccounts\Enums\SnapshotSource;
use App\Domain\PlayerAccounts\Models\CocAccount;
use App\Domain\PlayerAccounts\Models\CocAccountSnapshot;
use App\Domain\Users\Models\PrivacySettings;
use App\Domain\Users\Services\PrivacyPolicyResolver;
use App\Http\Middleware\HandleInertiaRequests;
use App\Models\User;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;

// The CoC account page /accounts/{ulid} (specs/18 §6, FR-COC-14), who sees it (P2-04 Q2) and what
// it shows.

beforeEach(function () {
    Date::setTestNow('2026-10-03 12:00:00');
    $this->owner = User::factory()->create(['username' => 'chief']);
    $this->clan = Clan::factory()->forTag('#2Q8URJ9L')->create(['name' => 'Night Owls', 'level' => 22]);
    $this->account = CocAccount::factory()->for($this->owner)->forTag('#2PQ8GRJC')->verified()->create([
        'ign' => 'Fixture Chief',
        'th_level' => 16,
        'trophies' => 5124,
        'best_trophies' => 5602,
        'war_stars' => 1480,
        'xp_level' => 231,
        'clan_id' => $this->clan->id,
        'clan_tag' => '#2Q8URJ9L',
        'clan_role' => 'coLeader',
        'league_id' => 29000022,
        'league_name' => 'Legend League',
        'heroes' => [['name' => 'Archer Queen', 'level' => 95, 'max_level' => 95, 'village' => 'home', 'super_troop_active' => false]],
        'troops' => [['name' => 'Barbarian', 'level' => 11, 'max_level' => 12, 'village' => 'home', 'super_troop_active' => false]],
        'raw_payload' => ['secret' => 'kept on the server'],
        'api_synced_at' => now()->subMinutes(12),
    ]);
});

function accountPage(?User $viewer, CocAccount $account): TestResponse
{
    return $viewer === null ? test()->get("/accounts/{$account->ulid}") : test()->actingAs($viewer)->get("/accounts/{$account->ulid}");
}

function accountGrids(?User $viewer, CocAccount $account): TestResponse
{
    $request = $viewer === null ? test() : test()->actingAs($viewer);

    return $request->withHeaders([
        'X-Inertia' => 'true',
        'X-Inertia-Version' => (string) app(HandleInertiaRequests::class)->version(request()),
        'X-Inertia-Partial-Component' => 'Accounts/Show',
        'X-Inertia-Partial-Data' => 'progression',
    ])->get("/accounts/{$account->ulid}");
}

it('shows a verified account to a guest, rendered from stored data', function () {
    accountPage(null, $this->account)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Accounts/Show')
            ->where('meta.title', 'Fixture Chief (#2PQ8GRJC)')
            ->where('account.card.ulid', $this->account->ulid)
            ->where('account.card.name', 'Fixture Chief')
            ->where('account.card.tag', '#2PQ8GRJC')
            ->where('account.card.status', 'verified')
            ->where('account.card.townHallLevel', 16)
            ->where('account.card.townHall.alt', 'Town Hall 16')
            ->where('account.card.leagueName', 'Legend League')
            ->where('account.card.clan.name', 'Night Owls')
            ->where('account.card.clan.roleLabel', 'Co-leader')
            ->where('account.card.clan.level', 22)
            ->where('account.card.clan.badge.kind', 'clan_badge')
            ->where('account.card.clanHidden', false)
            ->where('account.card.stale', false)
            ->where('account.card.syncedAgeSeconds', 720)
            ->where('account.stats.0', ['key' => 'trophies', 'label' => 'Trophies', 'value' => 5124, 'delta' => null])
            ->where('account.stats', fn ($stats) => collect($stats)->pluck('key')->all() === [
                'trophies', 'best_trophies', 'war_stars', 'xp_level', 'donations', 'donations_received', 'builder_trophies', 'best_builder_trophies',
            ])
            ->where('account.isOwn', false)
            ->where('account.canVerify', false)
            ->missing('progression')
            ->missing('account.card.rawPayload')
            ->missing('account.card.apiSyncFailures')
            ->missing('account.card.userId'));
});

it('heads the Builder Base tab with the Builder Hall, and leaves it out before one is built', function () {
    $this->account->update(['builder_hall_level' => 10]);
    accountPage(null, $this->account)->assertInertia(fn (Assert $page) => $page->where('account.builderHall.alt', 'Builder Hall 10'));

    $this->account->update(['builder_hall_level' => null]);
    accountPage(null, $this->account)->assertInertia(fn (Assert $page) => $page->where('account.builderHall', null));
});

it('shows the ranked league tier with the API\'s own tier icon', function () {
    // The large icon when the API sends one (fae1e19), the small one otherwise.
    $icon = 'https://api-assets.clashofclans.com/leaguetiers/326/legend.png';
    $this->account->update(['raw_payload' => ['leagueTier' => ['id' => 105000035, 'name' => 'Legend II', 'iconUrls' => ['small' => 'https://api-assets.clashofclans.com/leaguetiers/125/legend.png', 'large' => $icon]]]]);

    accountPage(null, $this->account)->assertInertia(fn (Assert $page) => $page
        ->where('account.card.leagueName', 'Legend II')
        ->where('account.card.league.url', $icon)
        ->where('account.card.league.alt', 'Legend II'));

    $this->account->update(['raw_payload' => ['leagueTier' => ['id' => 105000035, 'name' => 'Legend II', 'iconUrls' => ['small' => 'https://evil.test/x.png']]]]);

    accountPage(null, $this->account)->assertInertia(fn (Assert $page) => $page
        ->where('account.card.leagueName', 'Legend II')
        ->where('account.card.league.url', fn ($url) => $url === null || ! str_contains($url, 'evil.test')));
});

it('gives the Builder Base tab its own ranked data from the stored payload', function () {
    $this->account->update(['raw_payload' => ['bestBuilderBaseTrophies' => 4600, 'builderBaseLeague' => ['id' => 44000035, 'name' => 'Ruby League III']]]);

    accountPage(null, $this->account)->assertInertia(fn (Assert $page) => $page
        ->where('account.builderLeagueName', 'Ruby League III')
        ->where('account.builderLeague.alt', 'Ruby League III')
        ->where('account.stats.7', ['key' => 'best_builder_trophies', 'label' => 'Best Builder Base trophies', 'value' => 4600, 'delta' => null])
        ->missing('account.rawPayload'));

    $this->account->update(['raw_payload' => null]);

    accountPage(null, $this->account)->assertInertia(fn (Assert $page) => $page
        ->where('account.builderLeagueName', null)
        ->where('account.builderLeague', null)
        ->where('account.stats.7.value', null));
});

it('loads the grids as a deferred prop', function () {
    $response = accountGrids(null, $this->account);
    $troops = collect($response->json('props.progression'))->firstWhere('key', 'troops');

    expect(collect($troops['units'])->firstWhere('locked', false)['maxed'])->toBeFalse();

    $response
        ->assertOk()
        ->assertJsonPath('props.progression.0.key', 'heroes')
        ->assertJsonPath('props.progression.0.units.0.name', 'Barbarian King')
        ->assertJsonPath('props.progression.0.units.0.locked', true)
        ->assertJsonPath('props.progression.0.units.1.name', 'Archer Queen')
        ->assertJsonPath('props.progression.0.units.1.maxed', true)
        ->assertJsonPath('props.progression.0.village', 'home')
        ->assertJsonMissingPath('props.account.card.rawPayload');
});

it('gives every hidden account the same 404 as an unknown ulid, grids included', function (Closure $setup) {
    [$viewer, $account] = $setup->call($this);
    $unknown = $this->get('/accounts/01J00000000000000000000000');

    $hidden = accountPage($viewer, $account);

    $hidden->assertNotFound();
    expect($hidden->getContent())->toBe($unknown->getContent());
    accountGrids($viewer, $account)->assertNotFound();
})->with([
    'someone else\'s unverified row' => [fn () => [User::factory()->create(), CocAccount::factory()->forTag('#YC8V2QG9')->create()]],
    'a released row' => [fn () => [null, CocAccount::factory()->forTag('#YC8V2QG9')->released()->create()]],
    'a suspended row' => [fn () => [null, tap($this->account, fn (CocAccount $a) => $a->forceFill(['status' => 'suspended'])->save())]],
    'a banned owner' => [fn () => [null, tap($this->account, fn () => $this->owner->forceFill(['status' => 'banned'])->save())]],
    'an owner pending deletion' => [fn () => [null, tap($this->account, fn () => $this->owner->forceFill(['status' => 'pending_deletion'])->save())]],
    'a private profile' => [fn () => [User::factory()->create(), CocAccount::factory()->for(User::factory()->withPrivacy(['profile_visibility' => 'private']))->verified()->create()]],
    'a members-only profile to a guest' => [fn () => [null, CocAccount::factory()->for(User::factory()->withPrivacy(['profile_visibility' => 'members']))->verified()->create()]],
    'accounts hidden by the owner' => [fn () => [User::factory()->create(), CocAccount::factory()->for(User::factory()->withPrivacy(['show_coc_accounts' => false]))->verified()->create()]],
    'staff get no bypass' => [fn () => [User::factory()->admin()->create(), CocAccount::factory()->forTag('#YC8V2QG9')->create()]],
]);

it('shows a members-only profile\'s account to a signed-in viewer', function () {
    $account = CocAccount::factory()->for(User::factory()->withPrivacy(['profile_visibility' => 'members']))->verified()->create();

    accountPage(User::factory()->create(), $account)->assertOk();
});

it('shows a disputed account to others, under review', function () {
    $this->account->forceFill(['status' => 'disputed'])->save();

    accountPage(User::factory()->create(), $this->account)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('account.card.status', 'disputed')->where('account.card.statusLabel', 'Under review'));
});

it('shows the owner their own unverified row with the verify action', function () {
    $own = CocAccount::factory()->for($this->owner)->forTag('#YC8V2QG9')->create();

    accountPage($this->owner, $own)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('account.isOwn', true)->where('account.canVerify', true)->where('account.card.status', 'unverified'));
    expect(accountPage($this->owner, $own)->getContent())->toContain('noindex');
});

it('drops the clan for others when the owner hides it, never for the owner', function () {
    PrivacySettings::query()->whereKey($this->owner->id)->update(['show_clan' => false]);
    app(PrivacyPolicyResolver::class)->refresh($this->owner->id);

    accountPage(User::factory()->create(), $this->account)
        ->assertInertia(fn (Assert $page) => $page->where('account.card.clan', null)->where('account.card.clanHidden', true));
    accountPage($this->owner, $this->account)
        ->assertInertia(fn (Assert $page) => $page->where('account.card.clan.name', 'Night Owls')->where('account.card.clanHidden', false));
});

it('reads "no clan" for an account outside a clan', function () {
    $this->account->forceFill(['clan_id' => null, 'clan_tag' => null, 'clan_role' => null])->save();

    accountPage(null, $this->account)->assertInertia(fn (Assert $page) => $page->where('account.card.clan', null)->where('account.card.clanHidden', false));
});

it('compares stats with the newest snapshot at least delta_days old', function () {
    $days = (int) config('coc.display.delta_days');
    CocAccountSnapshot::factory()->for($this->account, 'account')->create(['captured_at' => now()->subDays($days + 5), 'trophies' => 4000, 'war_stars' => 1000, 'xp_level' => 200, 'best_trophies' => 5000, 'source' => SnapshotSource::Scheduled]);
    CocAccountSnapshot::factory()->for($this->account, 'account')->create(['captured_at' => now()->subDays($days + 1), 'trophies' => 5000, 'war_stars' => 1500, 'xp_level' => 231, 'best_trophies' => null, 'source' => SnapshotSource::Scheduled]);
    CocAccountSnapshot::factory()->for($this->account, 'account')->create(['captured_at' => now()->subDays($days - 1), 'trophies' => 5100, 'source' => SnapshotSource::Scheduled]);

    accountPage(null, $this->account)->assertInertia(fn (Assert $page) => $page
        ->where('account.deltaDays', $days)
        ->where('account.stats.0.delta', 124)
        ->where('account.stats.1.delta', null)
        ->where('account.stats.2.delta', -20)
        ->where('account.stats.3.delta', 0));
});

it('marks the account stale after three 404s or once the data is older than stale_hours', function (Closure $attributes, bool $stale, bool $notFound) {
    $this->account->forceFill($attributes())->save();

    accountPage(null, $this->account)->assertInertia(fn (Assert $page) => $page->where('account.card.stale', $stale)->where('account.notFound', $notFound));
})->with([
    'fresh' => [fn () => ['api_sync_failures' => (int) config('coc.sync.not_found_stale') - 1], false, false],
    'three 404s' => [fn () => ['api_sync_failures' => (int) config('coc.sync.not_found_stale')], true, true],
    'old data' => [fn () => ['api_synced_at' => now()->subHours((int) config('coc.display.stale_hours'))->subMinute()], true, false],
    'just inside the window' => [fn () => ['api_synced_at' => now()->subHours((int) config('coc.display.stale_hours'))->addMinute()], false, false],
    'never synced' => [fn () => ['api_synced_at' => null], true, false],
]);

it('reads "not available" fields as null rather than zero (specs/23 §5)', function () {
    $this->account->forceFill(['trophies' => null, 'xp_level' => null])->save();

    accountPage(null, $this->account)->assertInertia(fn (Assert $page) => $page->where('account.card.trophies', null)->where('account.stats.0.value', null)->where('account.stats.0.delta', null));
});

it('is indexable only for a verified account on a public, searchable profile', function () {
    expect(accountPage(null, $this->account)->getContent())->not->toContain('noindex');

    $this->account->forceFill(['status' => 'disputed'])->save();
    expect(accountPage(null, $this->account)->getContent())->toContain('noindex');
});

it('renders the page within the query budget', function () {
    foreach (range(1, 5) as $day) {
        CocAccountSnapshot::factory()->for($this->account, 'account')->create(['captured_at' => now()->subDays($day * 3)]);
    }
    DB::enableQueryLog();

    accountPage(User::factory()->create(), $this->account)->assertOk();

    expect(count(DB::getQueryLog()))->toBeLessThanOrEqual(25);
});

it('keeps a suspended owner\'s verified account visible, as their profile is', function () {
    $this->owner->forceFill(['status' => 'suspended'])->save();

    accountPage(null, $this->account)->assertOk();
});

it('shows the owner their own suspended row, and nobody else', function () {
    $this->account->forceFill(['status' => 'suspended'])->save();

    accountPage($this->owner, $this->account)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('account.card.status', 'suspended')->where('account.canVerify', false));
    accountPage(User::factory()->create(), $this->account)->assertNotFound();
});

it('lets a disputed owner verify from the page', function () {
    $this->account->forceFill(['status' => 'disputed'])->save();

    accountPage($this->owner, $this->account)->assertInertia(fn (Assert $page) => $page->where('account.canVerify', true));
});
