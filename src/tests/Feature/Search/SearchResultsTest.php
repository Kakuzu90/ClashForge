<?php

use App\Domain\Bases\Enums\BaseCategory;
use App\Domain\Bases\Enums\BaseStatus;
use App\Domain\Bases\Enums\BaseVisibility;
use App\Domain\Bases\Models\BaseLayout;
use App\Domain\Bases\Models\BaseTag;
use App\Domain\PlayerAccounts\Models\CocAccount;
use App\Domain\Users\Enums\ProfileVisibility;
use App\Domain\Users\Models\Profile;
use App\Http\Middleware\HandleInertiaRequests;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;

// P3-05: full-text results on `/search` (FR-SEARCH-1, -3, -6; specs/17 §2–§5). Postgres only:
// SQLite has no tsvector.

beforeEach(function () {
    if (DB::getDriverName() !== 'pgsql') {
        $this->markTestSkipped('Full-text search needs Postgres.');
    }

    Date::setTestNow('2026-10-07 12:00:00');
});

/**
 * @param  array<string, mixed>  $attributes
 * @param  array<string, int|float>  $metrics
 */
function searchBase(array $attributes = [], array $metrics = []): BaseLayout
{
    return BaseLayout::factory()->withMetrics($metrics)->create($attributes);
}

/**
 * @return array<string, mixed>
 */
function searchProps(TestResponse $response): array
{
    // A partial reload answers with JSON, a full visit with the page view.
    return $response->headers->has('X-Inertia') ? $response->json('props') : $response->viewData('page')['props'];
}

/**
 * @return list<string>
 */
function hitKeys(TestResponse $response, string $prop, string $key = 'ulid'): array
{
    return array_column(searchProps($response)[$prop], $key);
}

/**
 * "Load more" on one tab: a partial reload of that kind and the cursor.
 */
function searchMore(string $url, string $cursor, string $prop, ?User $viewer = null): TestResponse
{
    $test = $viewer === null ? test() : test()->actingAs($viewer);

    return $test->get($url.'&cursor='.urlencode($cursor), [
        'X-Inertia' => 'true',
        'X-Inertia-Version' => (string) app(HandleInertiaRequests::class)->version(request()),
        'X-Inertia-Partial-Component' => 'Search/Index',
        'X-Inertia-Partial-Data' => "{$prop},nextCursor",
    ]);
}

it('groups bases, players and accounts for one search, with nothing private', function () {
    $owner = User::factory()->withProfileData(['display_name' => 'Ring Master', 'bio' => 'I build ring bases.'])->create(['username' => 'ringer']);
    $account = CocAccount::factory()->for($owner)->verified()->create(['ign' => 'Ring Chief', 'th_level' => 16, 'trophies' => 5100]);
    $base = searchBase(['user_id' => $owner->id, 'title' => 'Anti-root ring', 'description' => 'Splits attackers']);
    searchBase(['title' => 'Box farm']);

    $response = $this->get('/search?q=ring')->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Search/Index')
        ->where('q', 'ring')
        ->where('meta.title', 'Search: ring')
        ->where('searched', ['bases', 'players', 'accounts'])
        ->where('bases.0.ulid', $base->ulid)
        ->has('bases', 1)
        ->where('players.0', ['username' => 'ringer', 'displayName' => 'Ring Master', 'avatarUrl' => null, 'bio' => 'I build ring bases.'])
        // The profile's list PlayerCard: XP level, name, tag, league; no clan.
        ->where('accounts.0.ulid', $account->ulid)
        ->where('accounts.0.tag', $account->tag)
        ->where('accounts.0.name', 'Ring Chief')
        ->where('accounts.0.xpLevel', $account->xp_level)
        ->where('accounts.0.clan', null)
        ->where('more', ['bases' => false, 'players' => false, 'accounts' => false])
        ->where('nextCursor', null)
        ->where('facets', null));

    $json = json_encode(searchProps($response), JSON_THROW_ON_ERROR);
    foreach (['layout_hash', 'base_link', 'search_vector', 'user_id', 'email', '"id"', 'rank', 'search_score'] as $private) {
        expect($json)->not->toContain($private);
    }
});

it('matches titles, tags and descriptions, the title ranking first', function () {
    $inTitle = searchBase(['title' => 'Island fortress']);
    $inDescription = searchBase(['title' => 'Plain box', 'description' => 'An island in the corner']);
    $inTag = searchBase(['title' => 'Other box']);
    $inTag->tags()->attach(BaseTag::factory()->create(['name' => 'island', 'slug' => 'island'])->id);

    $response = $this->get('/search?q=island&type=bases')->assertOk();

    expect(hitKeys($response, 'bases'))->toBe([$inTitle->ulid, $inTag->ulid, $inDescription->ulid]);
});

it('stems prose and matches names exactly', function () {
    searchBase(['title' => 'Farming the loot']);
    User::factory()->create(['username' => 'farmer_joe']);

    $response = $this->get('/search?q=farmed+loots')->assertOk();

    expect(searchProps($response)['bases'])->toHaveCount(1)
        ->and(searchProps($response)['players'])->toBe([]);
});

it('takes quoted phrases and exclusions without an error', function (string $q, int $hits) {
    searchBase(['title' => 'Ring of walls']);
    searchBase(['title' => 'Walls of ring']);

    $response = $this->get('/search?type=bases&q='.urlencode($q))->assertOk();

    expect(searchProps($response)['bases'])->toHaveCount($hits);
})->with([
    'phrase' => ['"ring of walls"', 1],
    'exclusion' => ['walls -ring', 0],
    'both words' => ['ring walls', 2],
]);

it('reads the Town Hall and category from the text and shows them as chips', function () {
    $hit = searchBase(['title' => 'Box', 'th_level' => 17, 'category' => BaseCategory::Anti3Star]);
    searchBase(['title' => 'Box', 'th_level' => 16, 'category' => BaseCategory::Anti3Star]);
    searchBase(['title' => 'Box', 'th_level' => 17, 'category' => BaseCategory::War]);

    $this->get('/search?q='.urlencode('TH17 Anti-3-Star base'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('bases.0.ulid', $hit->ulid)
        ->has('bases', 1)
        ->where('filters.thMin', 17)
        ->where('filters.category', 'anti_3_star')
        ->where('parsed', [
            ['key' => 'th', 'value' => '17', 'label' => 'Town Hall 17', 'match' => 'TH17'],
            ['key' => 'category', 'value' => 'anti_3_star', 'label' => 'Anti-3-Star', 'match' => 'Anti-3-Star'],
        ])
        // No words left for players and accounts to match.
        ->where('searched', ['bases']));
});

it('lets an explicit filter win over the text, without a chip', function () {
    searchBase(['title' => 'Box', 'th_level' => 15]);

    $this->get('/search?type=bases&q=TH17+box&th=15')->assertOk()->assertInertia(fn (Assert $page) => $page
        ->has('bases', 1)
        ->where('filters.thMin', 15)
        ->where('parsed', []));
});

it('filters accounts by a Town Hall named in the text', function () {
    $sixteen = CocAccount::factory()->verified()->create(['ign' => 'Echo', 'th_level' => 16]);
    CocAccount::factory()->verified()->create(['ign' => 'Echo', 'th_level' => 15]);

    $response = $this->get('/search?q=echo+th16&type=accounts')->assertOk();

    expect(hitKeys($response, 'accounts'))->toBe([$sixteen->ulid]);
});

it('shows every same-name account with its tag, highest trophies first', function () {
    $low = CocAccount::factory()->verified()->create(['ign' => 'Echo', 'trophies' => 3000]);
    $high = CocAccount::factory()->verified()->create(['ign' => 'Echo', 'trophies' => 5000]);

    $response = $this->get('/search?q=echo&type=accounts')->assertOk();

    expect(hitKeys($response, 'accounts'))->toBe([$high->ulid, $low->ulid])
        ->and(array_column(searchProps($response)['accounts'], 'tag'))->toBe([$high->tag, $low->tag]);
});

it('applies the /bases filters, sorts and facet counts on the Bases tab', function () {
    searchBase(['title' => 'Ring', 'th_level' => 16, 'category' => BaseCategory::War], ['likes_count' => 5]);
    $video = searchBase(['title' => 'Ring', 'th_level' => 16, 'category' => BaseCategory::War, 'has_video' => true], ['likes_count' => 9]);
    searchBase(['title' => 'Ring', 'th_level' => 15, 'category' => BaseCategory::Farming], ['likes_count' => 1]);

    $this->get('/search?q=ring&type=bases&th=16&category=war&video=1')->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('bases.0.ulid', $video->ulid)
        ->has('bases', 1)
        // Each facet ignores its own filter.
        ->where('facets.thLevels', [['value' => '16', 'count' => 1]])
        ->where('facets.categories', [['value' => 'war', 'count' => 1]]));

    $this->get('/search?q=ring&type=bases&sort=liked')->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('bases.0.likes', 9)
        ->where('bases.2.likes', 1)
        ->where('facets.thLevels', [['value' => '16', 'count' => 2], ['value' => '15', 'count' => 1]]));
});

it('blends text, trending and recency for best match', function () {
    $fresh = searchBase(['title' => 'Ring', 'published_at' => Date::now()->subDay()], ['trending_score' => 0.5]);
    $old = searchBase(['title' => 'Ring', 'published_at' => Date::now()->subDays(60)], ['trending_score' => 0.5]);
    $hot = searchBase(['title' => 'Ring', 'published_at' => Date::now()->subDays(60)], ['trending_score' => 20]);

    $response = $this->get('/search?q=ring&type=bases')->assertOk();

    expect(hitKeys($response, 'bases'))->toBe([$hot->ulid, $fresh->ulid, $old->ulid]);
});

it('pages each kind with an opaque cursor, without repeats, and caps anonymous paging', function () {
    config(['platform.search.per_page' => 2, 'platform.search.max_pages' => 2]);
    foreach (range(1, 5) as $i) {
        searchBase(['title' => "Ring {$i}", 'published_at' => Date::now()->subHours($i)]);
    }

    $first = $this->get('/search?q=ring&type=bases')->assertOk();
    $cursor = searchProps($first)['nextCursor'];
    $second = searchMore('/search?q=ring&type=bases', $cursor, 'bases')->assertOk();

    expect($cursor)->toBeString()->not->toContain('Ring')
        ->and(array_intersect(hitKeys($first, 'bases'), hitKeys($second, 'bases')))->toBe([])
        ->and(hitKeys($second, 'bases'))->toHaveCount(2)
        // Page 2 is the last an anonymous visitor may load.
        ->and(searchProps($second)['nextCursor'])->toBeNull();

    $viewer = User::factory()->create();
    $signedIn = searchMore('/search?q=ring&type=bases', searchProps($this->actingAs($viewer)->get('/search?q=ring&type=bases'))['nextCursor'], 'bases', $viewer);
    expect(searchProps($signedIn)['nextCursor'])->toBeString();

    $this->get('/search?q=box&type=bases&cursor='.urlencode($cursor))->assertSessionHasErrors('cursor');
});

it('pages players and accounts too', function () {
    config(['platform.search.per_page' => 2]);
    foreach (range(1, 3) as $i) {
        User::factory()->create(['username' => "echo_{$i}"]);
        CocAccount::factory()->verified()->create(['ign' => "Echo {$i}", 'trophies' => 1000 * $i]);
    }

    foreach (['players' => 'username', 'accounts' => 'ulid'] as $kind => $key) {
        $first = $this->get("/search?q=echo&type={$kind}")->assertOk();
        $second = searchMore("/search?q=echo&type={$kind}", searchProps($first)['nextCursor'], $kind)->assertOk();

        expect(array_merge(hitKeys($first, $kind, $key), hitKeys($second, $kind, $key)))->toHaveCount(3)->toBe(array_unique(array_merge(hitKeys($first, $kind, $key), hitKeys($second, $kind, $key))))
            ->and(searchProps($second)['nextCursor'])->toBeNull();
    }
});

it('says when a grouped kind has more, for "See all"', function () {
    config(['platform.search.group_size.players' => 1]);
    User::factory()->count(2)->sequence(['username' => 'echo_a'], ['username' => 'echo_b'])->create();

    $this->get('/search?q=echo')->assertOk()->assertInertia(fn (Assert $page) => $page
        ->has('players', 1)
        ->where('more.players', true)
        ->where('nextCursor', null));
});

it('lists only what anyone may see, enforced in the query', function () {
    $visible = searchBase(['title' => 'Echo visible']);
    foreach ([
        ['visibility' => BaseVisibility::Unlisted],
        ['visibility' => BaseVisibility::Private],
        ['status' => BaseStatus::Processing],
        ['status' => BaseStatus::Hidden],
        ['status' => BaseStatus::Removed],
        ['deleted_at' => Date::now()],
        ['user_id' => User::factory()->banned()],
        ['user_id' => User::factory()->suspended()],
        ['user_id' => User::factory()->pendingDeletion()],
    ] as $attributes) {
        searchBase(['title' => 'Echo hidden', ...$attributes]);
    }
    // Turning search off hides the player, not their public bases.
    $quiet = searchBase(['title' => 'Echo quiet', 'user_id' => User::factory()->withPrivacy(['searchable' => false])]);

    $players = [
        'echo_public' => [],
        'echo_members' => ['profile_visibility' => ProfileVisibility::Members],
        'echo_private' => ['profile_visibility' => ProfileVisibility::Private],
        'echo_quiet' => ['searchable' => false],
    ];
    foreach ($players as $username => $privacy) {
        User::factory()->withPrivacy($privacy)->create(['username' => $username]);
    }
    User::factory()->banned()->create(['username' => 'echo_banned']);
    User::factory()->suspended()->create(['username' => 'echo_suspended']);

    $account = CocAccount::factory()->verified()->create(['ign' => 'Echo shown']);
    CocAccount::factory()->disputed()->create(['ign' => 'Echo disputed']);
    CocAccount::factory()->create(['ign' => 'Echo unverified']);
    CocAccount::factory()->verified()->released()->create(['ign' => 'Echo released']);
    CocAccount::factory()->verified()->for(User::factory()->withPrivacy(['show_coc_accounts' => false]))->create(['ign' => 'Echo private']);
    CocAccount::factory()->verified()->for(User::factory()->suspended())->create(['ign' => 'Echo suspended']);

    $guest = $this->get('/search?q=echo')->assertOk();

    expect(hitKeys($guest, 'bases'))->toEqualCanonicalizing([$visible->ulid, $quiet->ulid])
        ->and(hitKeys($guest, 'players', 'username'))->toBe(['echo_public'])
        ->and(array_column(searchProps($guest)['accounts'], 'name'))->toEqualCanonicalizing(['Echo shown', 'Echo disputed']);

    $member = $this->actingAs(User::factory()->create())->get('/search?q=echo')->assertOk();
    expect(hitKeys($member, 'players', 'username'))->toEqualCanonicalizing(['echo_public', 'echo_members']);
});

it('caches an anonymous first page for a minute, and never a signed-in one', function () {
    searchBase(['title' => 'Ring']);
    $this->get('/search?q=ring')->assertOk();
    searchBase(['title' => 'Ring two']);

    expect(searchProps($this->get('/search?q=ring'))['bases'])->toHaveCount(1)
        ->and(searchProps($this->actingAs(User::factory()->create())->get('/search?q=ring'))['bases'])->toHaveCount(2);

    Date::setTestNow(Date::now()->addSeconds(61));
    Cache::flush();
    expect(searchProps($this->get('/search?q=ring'))['bases'])->toHaveCount(2);
});

it('keeps the vectors current through renames, tag changes and edits', function () {
    $user = User::factory()->create(['username' => 'oldname']);
    $base = searchBase(['title' => 'Plain', 'user_id' => $user->id]);
    $tag = BaseTag::factory()->create(['name' => 'sidewinder', 'slug' => 'sidewinder']);

    $user->forceFill(['username' => 'newname'])->save();
    Profile::query()->where('user_id', $user->id)->update(['bio' => 'Loves gibbons']);
    $base->tags()->attach($tag->id);
    $base->forceFill(['description' => 'Has a moat'])->save();

    expect(hitKeys($this->get('/search?q=newname&type=players'), 'players', 'username'))->toBe(['newname'])
        ->and(searchProps($this->get('/search?q=oldname&type=players'))['players'])->toBe([])
        ->and(searchProps($this->get('/search?q=gibbons&type=players'))['players'])->toHaveCount(1)
        ->and(hitKeys($this->get('/search?q=sidewinder&type=bases'), 'bases'))->toBe([$base->ulid])
        ->and(hitKeys($this->get('/search?q=moat&type=bases'), 'bases'))->toBe([$base->ulid]);

    $base->tags()->detach($tag->id);
    $tag->forceFill(['name' => 'renamed'])->save();
    Cache::flush();
    expect(searchProps($this->get('/search?q=sidewinder&type=bases'))['bases'])->toBe([]);
});

it('rebuilds the vectors on demand', function () {
    searchBase(['title' => 'Ring']);
    DB::statement('UPDATE base_layouts SET search_vector = NULL');
    DB::statement('UPDATE profiles SET search_vector = NULL');

    $this->artisan('search:reindex', ['type' => 'bases'])->expectsOutputToContain('Reindexed 1 rows')->assertSuccessful();
    $this->artisan('search:reindex')->assertSuccessful();
    $this->artisan('search:reindex', ['type' => 'clans'])->assertFailed();

    expect(DB::table('profiles')->whereNull('search_vector')->count())->toBe(0)
        ->and(searchProps($this->get('/search?q=ring&type=bases'))['bases'])->toHaveCount(1);
});

it('runs every search query through an index, never a scan of the searched table', function () {
    searchBase(['title' => 'Echo', 'th_level' => 16, 'category' => BaseCategory::War, 'has_video' => true]);
    CocAccount::factory()->verified()->create(['ign' => 'Echo', 'th_level' => 16]);
    User::factory()->create(['username' => 'echo_one']);

    $queries = [];
    DB::listen(function ($query) use (&$queries): void {
        if (str_contains($query->sql, '@@')) {
            $queries[] = [$query->sql, $query->bindings];
        }
    });

    $viewer = User::factory()->create();
    foreach ([
        '/search?q=echo',
        '/search?q=echo+th16&type=accounts',
        '/search?q=echo&type=players',
        '/search?q=echo&type=bases&th=16&category=war&video=1&min_likes=1&tag=ring',
        '/search?q=echo&type=bases&sort=liked',
    ] as $url) {
        $this->actingAs($viewer)->get($url)->assertOk();
    }

    expect($queries)->not->toBeEmpty();

    DB::statement('SET enable_seqscan = off');
    foreach ($queries as [$sql, $bindings]) {
        $plan = implode("\n", array_map(fn (stdClass $row): string => (string) $row->{'QUERY PLAN'}, DB::select("EXPLAIN {$sql}", $bindings)));
        expect($plan)->not->toMatch('/Seq Scan on (base_layouts|profiles|coc_accounts)\b/');
    }
    DB::statement('SET enable_seqscan = on');
});

it('answers a grouped search within the query budget', function () {
    foreach (range(1, 6) as $i) {
        $owner = User::factory()->create(['username' => "echo_{$i}"]);
        $account = CocAccount::factory()->for($owner)->verified()->create(['ign' => "Echo {$i}"]);
        searchBase(['title' => "Echo {$i}", 'user_id' => $owner->id, 'coc_account_id' => $account->id]);
    }

    DB::enableQueryLog();
    $this->actingAs(User::factory()->create())->get('/search?q=echo')->assertOk();

    expect(count(DB::getQueryLog()))->toBeLessThanOrEqual(25);
});
