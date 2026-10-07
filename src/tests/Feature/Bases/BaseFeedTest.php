<?php

use App\Domain\Auth\Events\AccountDeletionRequested;
use App\Domain\Bases\Enums\BaseCategory;
use App\Domain\Bases\Enums\BaseStatus;
use App\Domain\Bases\Enums\BaseVisibility;
use App\Domain\Bases\Events\BasePublished;
use App\Domain\Bases\Models\BaseLayout;
use App\Domain\Bases\Models\BaseTag;
use App\Domain\Media\Enums\MediaCollection;
use App\Domain\Media\Enums\VariantName;
use App\Domain\Media\Models\Media;
use App\Domain\Media\Models\MediaVariant;
use App\Domain\Moderation\Events\SanctionApplied;
use App\Domain\Moderation\Events\SanctionLifted;
use App\Domain\PlayerAccounts\Enums\ReleaseReason;
use App\Domain\PlayerAccounts\Events\CocAccountReleased;
use App\Domain\PlayerAccounts\Models\CocAccount;
use App\Domain\Users\Enums\ProfileVisibility;
use App\Domain\Users\Events\PrivacySettingsChanged;
use App\Http\Middleware\HandleInertiaRequests;
use App\Models\User;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;

// P3-03: the feed on `/bases` and `/` (FR-BASE-13; specs/17 §4, §6; specs/21 §3).

beforeEach(function () {
    Date::setTestNow('2026-10-07 12:00:00');
    config(['media.cdn_url' => 'https://cdn.test']);
});

/**
 * A published public base with its metrics row.
 *
 * @param  array<string, mixed>  $attributes
 * @param  array<string, int|float>  $metrics
 */
function feedBase(array $attributes = [], array $metrics = []): BaseLayout
{
    return BaseLayout::factory()->withMetrics($metrics)->create($attributes);
}

/**
 * @return list<string>
 */
function feedUlids(TestResponse $response): array
{
    return array_column($response->viewData('page')['props']['cards'], 'ulid');
}

/**
 * The next page as "Load more" asks for it: a partial reload of the cards and the cursor.
 */
function loadMore(string $url, string $cursor, string $component = 'Bases/Index', ?User $viewer = null): TestResponse
{
    $test = $viewer === null ? test() : test()->actingAs($viewer);

    // Headers per request: `withHeaders` would stick to every later request in the test.
    return $test->get($url.(str_contains($url, '?') ? '&' : '?').'cursor='.urlencode($cursor), [
        'X-Inertia' => 'true',
        'X-Inertia-Version' => (string) app(HandleInertiaRequests::class)->version(request()),
        'X-Inertia-Partial-Component' => $component,
        'X-Inertia-Partial-Data' => 'cards,nextCursor',
    ]);
}

it('renders /bases with the cards, filters and options, and nothing private', function () {
    $author = User::factory()->withProfileData(['display_name' => 'Ring Master'])->create();
    $account = CocAccount::factory()->for($author)->verified()->create(['ign' => 'Chief Ana', 'th_level' => 16]);
    $base = feedBase(['user_id' => $author->id, 'coc_account_id' => $account->id, 'title' => 'Anti-root ring'], ['likes_count' => 7, 'copies_count' => 3, 'views_count' => 90]);

    $response = $this->get('/bases')->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Bases/Index')
        ->where('meta.title', 'Base layouts')
        ->where('filters', ['thMin' => null, 'thMax' => null, 'category' => null, 'tag' => null, 'minLikes' => null, 'hasVideo' => false, 'sort' => 'trending'])
        ->where('options.thMin', config('bases.th_min'))
        ->where('options.thMax', config('bases.th_max'))
        ->has('options.categories', count(BaseCategory::cases()))
        ->has('options.sorts', 4)
        ->where('nextCursor', null)
        ->has('cards', 1, fn (Assert $card) => $card
            ->where('ulid', $base->ulid)
            ->where('slug', $base->slug)
            ->where('title', 'Anti-root ring')
            ->where('thLevel', 16)
            ->where('category', 'war')
            ->where('hasVideo', false)
            ->where('cover', null)
            ->where('likes', 7)->where('copies', 3)->where('views', 90)
            ->where('author', ['username' => $author->username, 'displayName' => 'Ring Master', 'avatarUrl' => null])
            ->where('credit', ['ulid' => $account->ulid, 'name' => 'Chief Ana', 'thLevel' => 16])));

    expect(json_encode($response->viewData('page')['props']['cards']))
        ->not->toContain('layout_hash', 'layoutHash', 'base_link', 'baseLink', 'flagged', 'user_id', 'userId', $base->layout_hash, 'link.clashofclans.com');
});

it('lists only published public bases of authors whose content is visible', function () {
    $shown = [
        'plain' => feedBase(),
        'restricted author' => feedBase(['user_id' => User::factory()->restricted()->create()->id]),
        'suspension over' => feedBase(['user_id' => User::factory()->suspended(Date::now()->subMinute())->create()->id]),
    ];
    $hidden = [
        feedBase(['visibility' => BaseVisibility::Unlisted]),
        feedBase(['visibility' => BaseVisibility::Private]),
        BaseLayout::factory()->processing()->withMetrics()->create(),
        feedBase(['status' => BaseStatus::Hidden]),
        feedBase(['status' => BaseStatus::Removed]),
        tap(feedBase())->delete(),
        feedBase(['user_id' => User::factory()->suspended(Date::now()->addDay())->create()->id]),
        feedBase(['user_id' => User::factory()->suspended()->create()->id]),
        feedBase(['user_id' => User::factory()->banned()->create()->id]),
        feedBase(['user_id' => User::factory()->pendingDeletion()->create()->id]),
    ];

    $listed = feedUlids($this->get('/bases'));

    expect($listed)->toEqualCanonicalizing(array_values(array_map(fn (BaseLayout $b) => $b->ulid, $shown)))
        ->and(array_intersect($listed, array_map(fn (BaseLayout $b) => $b->ulid, $hidden)))->toBe([]);
});

it('filters by Town Hall, category, tag, likes and video, combined', function () {
    $tag = BaseTag::factory()->create(['name' => 'ring-base', 'slug' => 'ring-base']);
    $match = feedBase(['th_level' => 16, 'category' => BaseCategory::Anti3Star, 'has_video' => true], ['likes_count' => 20]);
    $match->tags()->attach($tag);
    $others = [
        feedBase(['th_level' => 14, 'category' => BaseCategory::Anti3Star, 'has_video' => true], ['likes_count' => 20]),
        feedBase(['th_level' => 16, 'category' => BaseCategory::War, 'has_video' => true], ['likes_count' => 20]),
        feedBase(['th_level' => 16, 'category' => BaseCategory::Anti3Star, 'has_video' => false], ['likes_count' => 20]),
        feedBase(['th_level' => 16, 'category' => BaseCategory::Anti3Star, 'has_video' => true], ['likes_count' => 2]),
        feedBase(['th_level' => 16, 'category' => BaseCategory::Anti3Star, 'has_video' => true], ['likes_count' => 20]),
    ];
    foreach (array_slice($others, 0, 4) as $other) {
        $other->tags()->attach($tag);
    }

    expect(feedUlids($this->get('/bases?th=15-17&category=anti_3_star&tag=Ring+Base&min_likes=10&video=1')))->toBe([$match->ulid])
        ->and(feedUlids($this->get('/bases?th=14')))->toBe([$others[0]->ulid])
        ->and(feedUlids($this->get('/bases?category=war')))->toBe([$others[1]->ulid])
        ->and($this->get('/bases?tag=ring-base&th=all')->viewData('page')['props']['filters'])->toMatchArray(['tag' => 'ring-base', 'thMin' => null]);
});

it('sorts by trending, newest, likes and copies, newest id first on ties', function () {
    $a = feedBase(['published_at' => Date::now()->subHours(3)], ['trending_score' => 0.5, 'likes_count' => 1, 'copies_count' => 9]);
    $b = feedBase(['published_at' => Date::now()->subHours(1)], ['trending_score' => 2.25, 'likes_count' => 5, 'copies_count' => 9]);
    $c = feedBase(['published_at' => Date::now()->subHours(2)], ['trending_score' => 0.5, 'likes_count' => 3, 'copies_count' => 1]);

    expect(feedUlids($this->get('/bases')))->toBe([$b->ulid, $c->ulid, $a->ulid])
        ->and(feedUlids($this->get('/bases?sort=new')))->toBe([$b->ulid, $c->ulid, $a->ulid])
        ->and(feedUlids($this->get('/bases?sort=liked')))->toBe([$b->ulid, $c->ulid, $a->ulid])
        ->and(feedUlids($this->get('/bases?sort=copied')))->toBe([$b->ulid, $a->ulid, $c->ulid]);
});

it('pages through every sort with the cursor, without repeats or gaps, ties and float scores included', function (string $sort) {
    config(['bases.feed.per_page' => 2]);
    $scores = [0.1, 1 / 3, 1 / 3, 2 / 3, 0.1, 7.123456789, 1 / 3];
    foreach ($scores as $i => $score) {
        feedBase(['published_at' => Date::now()->subMinutes($i % 3)], ['trending_score' => $score, 'likes_count' => $i % 2, 'copies_count' => $i % 3]);
    }

    $first = $this->get("/bases?sort={$sort}");
    $seen = feedUlids($first);
    $cursor = $first->viewData('page')['props']['nextCursor'];
    $pages = 1;

    while ($cursor !== null) {
        $next = loadMore("/bases?sort={$sort}", $cursor)->assertOk();
        $seen = [...$seen, ...array_column($next->json('props.cards'), 'ulid')];
        $cursor = $next->json('props.nextCursor');
        expect(++$pages)->toBeLessThan(10);
    }

    expect($seen)->toHaveCount(count($scores))
        ->and(array_unique($seen))->toHaveCount(count($scores))
        ->and($pages)->toBe(4);
})->with(['trending', 'new', 'liked', 'copied']);

it('marks the cards as a merge prop so "Load more" appends', function () {
    config(['bases.feed.per_page' => 1]);
    feedBase();
    feedBase();

    $first = $this->get('/bases');
    $page = $first->viewData('page');

    expect($page['mergeProps'] ?? [])->toContain('cards')
        ->and(loadMore('/bases', $page['props']['nextCursor'])->json('props'))->toHaveKeys(['cards', 'nextCursor'])->not->toHaveKey('filters');
});

it('caps anonymous paging at the configured page, not signed-in paging', function () {
    config(['bases.feed.per_page' => 1, 'bases.feed.max_pages' => 2]);
    foreach (range(1, 4) as $i) {
        feedBase(['published_at' => Date::now()->subMinutes($i)]);
    }
    $viewer = User::factory()->create();

    $guestCursor = $this->get('/bases?sort=new')->viewData('page')['props']['nextCursor'];
    expect(loadMore('/bases?sort=new', $guestCursor)->json('props.nextCursor'))->toBeNull();

    $memberCursor = $this->actingAs($viewer)->get('/bases?sort=new')->viewData('page')['props']['nextCursor'];
    $third = loadMore('/bases?sort=new', $memberCursor, viewer: $viewer)->json('props.nextCursor');
    expect($third)->not->toBeNull();

    // A page past the cap is refused to a guest even with a genuine cursor.
    auth()->logout();
    $this->get('/bases?sort=new&cursor='.urlencode($third))->assertRedirect('/bases')->assertSessionHasErrors('cursor');
});

it('refuses tampered, foreign and malformed cursors as a field error', function (Closure $cursor) {
    config(['bases.feed.per_page' => 1]);
    feedBase();
    feedBase();
    $real = $this->get('/bases?sort=new')->viewData('page')['props']['nextCursor'];

    $this->get('/bases?sort=trending&cursor='.urlencode($cursor($real)))
        ->assertRedirect('/bases')
        ->assertSessionHasErrors(['cursor' => 'That page link is not valid. Start from the first page.']);
})->with([
    'another sort' => [fn (string $real) => $real],
    'truncated' => [fn (string $real) => substr($real, 0, -12)],
    'readable payload' => [fn () => rtrim(strtr(base64_encode('{"v":"9","i":1,"p":2}'), '+/', '-_'), '=')],
    'garbage' => [fn () => '%%%'],
]);

it('refuses invalid filters as field errors', function (string $query, string $field) {
    $this->get("/bases?{$query}")->assertRedirect('/bases')->assertSessionHasErrors($field);
})->with([
    'TH over the max' => [fn () => 'th='.(config('bases.th_max') + 1), 'th'],
    'TH range backwards' => ['th=17-15', 'th'],
    'TH not a number' => ['th=sixteen', 'th'],
    'unknown category' => ['category=castle', 'category'],
    'tag too long' => [fn () => 'tag='.str_repeat('a', (int) config('bases.tag_max_length') + 1), 'tag'],
    'likes below 1' => ['min_likes=0', 'min_likes'],
    'likes over the max' => [fn () => 'min_likes='.(config('bases.feed.min_likes_max') + 1), 'min_likes'],
    'unknown sort' => ['sort=random', 'sort'],
]);

it('starts a signed-in home feed at the featured account\'s Town Hall ±1, and lets go of it', function () {
    $viewer = User::factory()->create();
    CocAccount::factory()->for($viewer)->verified()->featured()->create(['th_level' => 16]);
    $near = feedBase(['th_level' => 15]);
    $far = feedBase(['th_level' => 12]);
    $spread = (int) config('bases.feed.default_th_spread');

    $this->actingAs($viewer)->get('/')->assertInertia(fn (Assert $page) => $page
        ->component('Home/Index')
        ->where('filters.thMin', 16 - $spread)
        ->where('filters.thMax', 16 + $spread)
        ->where('thFromAccount', true)
        ->where('cards.0.ulid', $near->ulid)
        ->has('cards', 1));

    $this->actingAs($viewer)->get('/?th=all')->assertInertia(fn (Assert $page) => $page->where('filters.thMin', null)->where('thFromAccount', false)->has('cards', 2));
    $this->actingAs($viewer)->get('/?th=12')->assertInertia(fn (Assert $page) => $page->where('cards.0.ulid', $far->ulid)->where('thFromAccount', false));

    // Guests and members without an account get every Town Hall.
    auth()->logout();
    $this->get('/')->assertInertia(fn (Assert $page) => $page->where('filters.thMin', null)->has('cards', 2));
});

it('offers the home feed three sorts and ignores the /bases-only filters', function () {
    $video = feedBase(['has_video' => true, 'category' => BaseCategory::War]);
    feedBase();

    $this->get('/?video=1&category=farming&sort=copied')->assertInertia(fn (Assert $page) => $page
        ->where('options.sorts', [['value' => 'trending', 'label' => 'Trending'], ['value' => 'new', 'label' => 'New'], ['value' => 'copied', 'label' => 'Most copied']])
        ->where('filters.hasVideo', false)
        ->where('filters.category', null)
        ->where('filters.sort', 'copied')
        ->has('cards', 2));

    $this->get('/?sort=liked')->assertRedirect('/')->assertSessionHasErrors('sort');
    expect($video->exists)->toBeTrue();
});

it('shows a credit only for a held account whose holder shows their accounts', function (Closure $setup, bool $shown) {
    $author = User::factory()->create();
    $account = CocAccount::factory()->for($author)->verified()->create();
    feedBase(['user_id' => $author->id, 'coc_account_id' => $account->id]);
    $setup($author, $account);

    $credit = $this->get('/bases')->viewData('page')['props']['cards'][0]['credit'];

    expect($credit === null)->toBe(! $shown);
})->with([
    'public profile' => [fn () => null, true],
    'accounts hidden' => [fn (User $u) => DB::table('privacy_settings')->where('user_id', $u->id)->update(['show_coc_accounts' => false]), false],
    'private profile' => [fn (User $u) => DB::table('privacy_settings')->where('user_id', $u->id)->update(['profile_visibility' => ProfileVisibility::Private->value]), false],
    'members-only profile' => [fn (User $u) => DB::table('privacy_settings')->where('user_id', $u->id)->update(['profile_visibility' => ProfileVisibility::Members->value]), false],
    'no privacy row' => [fn (User $u) => DB::table('privacy_settings')->where('user_id', $u->id)->delete(), false],
    'account released' => [fn (User $u, CocAccount $a) => $a->forceFill(['status' => 'unverified', 'user_id' => null])->save(), false],
]);

it('covers a card with the first screenshot, else the video poster, else nothing', function () {
    $type = (new BaseLayout)->getMorphClass();
    $attach = function (BaseLayout $base, MediaCollection $collection, VariantName $variant, int $position, int $width) use ($type): void {
        $media = Media::factory()->collection($collection)->ready()->create(['attachable_type' => $type, 'attachable_id' => $base->id, 'position' => $position, 'expires_at' => null]);
        MediaVariant::factory()->create(['media_id' => $media->id, 'variant' => $variant, 'path' => "public/{$collection->value}/{$media->ulid}/{$variant->value}.webp", 'width' => $width, 'height' => 450]);
    };
    $shots = feedBase(['published_at' => Date::now()->subMinutes(1)]);
    $attach($shots, MediaCollection::BaseScreenshot, VariantName::Card, 1, 801);
    $attach($shots, MediaCollection::BaseScreenshot, VariantName::Card, 0, 800);
    $attach($shots, MediaCollection::BaseVideo, VariantName::Poster, 0, 1280);
    $video = feedBase(['published_at' => Date::now()->subMinutes(2)]);
    $attach($video, MediaCollection::BaseVideo, VariantName::Poster, 0, 1280);
    feedBase(['published_at' => Date::now()->subMinutes(3)]);

    $cards = $this->get('/bases?sort=new')->viewData('page')['props']['cards'];

    expect($cards[0]['cover'])->toMatchArray(['name' => 'card', 'width' => 800])
        ->and($cards[0]['cover']['url'])->toStartWith('https://cdn.test/public/base_screenshot/')
        ->and($cards[1]['cover'])->toMatchArray(['name' => 'poster', 'width' => 1280])
        ->and($cards[2]['cover'])->toBeNull();
});

it('stays within the query budget with a full page of authors, avatars, credits and covers', function () {
    $type = (new BaseLayout)->getMorphClass();
    foreach (range(1, (int) config('bases.feed.per_page')) as $i) {
        $author = User::factory()->create();
        $account = CocAccount::factory()->for($author)->verified()->create();
        $base = feedBase(['user_id' => $author->id, 'coc_account_id' => $account->id]);
        $media = Media::factory()->collection(MediaCollection::BaseScreenshot)->ready()->create(['attachable_type' => $type, 'attachable_id' => $base->id]);
        MediaVariant::factory()->create(['media_id' => $media->id]);
    }
    $viewer = User::factory()->create();
    CocAccount::factory()->for($viewer)->verified()->featured()->create(['th_level' => 16]);

    foreach ([null, $viewer] as $who) {
        foreach (['/bases', '/'] as $url) {
            auth()->logout();
            DB::flushQueryLog();
            DB::enableQueryLog();
            ($who === null ? $this : $this->actingAs($who))->get($url)->assertOk()->assertInertia(fn (Assert $page) => $page->has('cards', (int) config('bases.feed.per_page')));
            expect(count(DB::getQueryLog()))->toBeLessThanOrEqual(25);
        }
    }
});

it('caches the viewer-independent lists and drops them when a base is published or an author sanctioned', function () {
    $first = feedBase();
    expect(feedUlids($this->get('/bases')))->toBe([$first->ulid]);

    // Written behind the cache's back: still the cached page.
    $quiet = feedBase();
    expect(feedUlids($this->get('/bases')))->toBe([$first->ulid]);

    BasePublished::dispatch($quiet->ulid, $quiet->user_id);
    expect(feedUlids($this->get('/bases')))->toEqualCanonicalizing([$first->ulid, $quiet->ulid]);

    $author = User::query()->findOrFail($first->user_id);
    $author->forceFill(['status' => 'banned'])->save();
    SanctionApplied::dispatch($author->id, 1);
    expect(feedUlids($this->get('/bases')))->toBe([$quiet->ulid]);
});

it('runs filtered lists live', function () {
    feedBase(['has_video' => true]);
    expect(feedUlids($this->get('/bases?video=1')))->toHaveCount(1);

    feedBase(['has_video' => true]);
    expect(feedUlids($this->get('/bases?video=1')))->toHaveCount(2);
});

it('loads more of the signed-in default feed when the page sends its filters with the cursor', function () {
    config(['bases.feed.per_page' => 2]);
    $viewer = User::factory()->create();
    CocAccount::factory()->for($viewer)->verified()->featured()->create(['th_level' => 16]);
    foreach (range(1, 5) as $i) {
        feedBase(['th_level' => 15 + ($i % 3), 'published_at' => Date::now()->subMinutes($i)]);
    }

    $first = $this->actingAs($viewer)->get('/');
    $props = $first->viewData('page')['props'];
    $seen = array_column($props['cards'], 'ulid');
    $cursor = $props['nextCursor'];

    // What the page sends: `th` as shown, plus the cursor.
    while ($cursor !== null) {
        $next = loadMore('/?th=15-17', $cursor, 'Home/Index', $viewer)->assertOk();
        $seen = [...$seen, ...array_column($next->json('props.cards'), 'ulid')];
        $cursor = $next->json('props.nextCursor');
    }

    expect($seen)->toHaveCount(5)->and(array_unique($seen))->toHaveCount(5);
});

it('ignores the /bases-only filters on the home feed whatever their value', function (string $query) {
    feedBase();

    $this->get("/?{$query}")->assertOk()->assertInertia(fn (Assert $page) => $page->component('Home/Index')->has('cards', 1));
})->with(['category=castle', 'tag=!!!', 'tag[]=x', 'category[]=war', 'min_likes=abc', 'video=maybe']);

it('brings a sanctioned author\'s bases back, untouched, when the sanction is lifted', function () {
    $author = User::factory()->create();
    $base = feedBase(['user_id' => $author->id], ['likes_count' => 4]);
    expect(feedUlids($this->get('/bases')))->toBe([$base->ulid]);

    $author->forceFill(['status' => 'banned'])->save();
    SanctionApplied::dispatch($author->id, 1);
    expect(feedUlids($this->get('/bases')))->toBe([]);

    $author->forceFill(['status' => 'active'])->save();
    SanctionLifted::dispatch($author->id, 1, false);
    expect($this->get('/bases')->viewData('page')['props']['cards'])->sequence(fn ($card) => $card->ulid->toBe($base->ulid)->likes->toBe(4));
});

it('drops a credit from cached pages when the account changes hands', function () {
    $author = User::factory()->create();
    $account = CocAccount::factory()->for($author)->verified()->create();
    feedBase(['user_id' => $author->id, 'coc_account_id' => $account->id]);
    expect($this->get('/bases')->viewData('page')['props']['cards'][0]['credit'])->not->toBeNull();

    $account->forceFill(['status' => 'unverified', 'user_id' => null])->save();
    CocAccountReleased::dispatch($account->id, $author->id, ReleaseReason::Detach);

    expect($this->get('/bases')->viewData('page')['props']['cards'][0]['credit'])->toBeNull();
});

it('keeps the internal base id out of the cursor', function () {
    config(['bases.feed.per_page' => 1]);
    $base = feedBase(['published_at' => Date::now()]);
    feedBase(['published_at' => Date::now()->subMinute()]);

    $cursor = $this->get('/bases?sort=new')->viewData('page')['props']['nextCursor'];

    expect(base64_decode($cursor, true) ?: '')->not->toContain('"i":'.$base->id)
        ->and($cursor)->not->toContain((string) $base->published_at);
});

it('shows only the username of an author whose profile is not public', function (string $visibility) {
    $author = User::factory()->withProfileData(['display_name' => 'Real Name'])->withPrivacy(['profile_visibility' => $visibility])->create();
    feedBase(['user_id' => $author->id]);

    expect($this->get('/bases')->viewData('page')['props']['cards'][0]['author'])
        ->toBe(['username' => $author->username, 'displayName' => null, 'avatarUrl' => null]);
})->with([ProfileVisibility::Private->value, ProfileVisibility::Members->value]);

it('drops cached pages when an author changes their privacy or asks to be deleted', function () {
    $author = User::factory()->withProfileData(['display_name' => 'Ring Master'])->create();
    feedBase(['user_id' => $author->id]);
    expect($this->get('/bases')->viewData('page')['props']['cards'][0]['author']['displayName'])->toBe('Ring Master');

    DB::table('privacy_settings')->where('user_id', $author->id)->update(['profile_visibility' => ProfileVisibility::Private->value]);
    PrivacySettingsChanged::dispatch($author->id);
    expect($this->get('/bases')->viewData('page')['props']['cards'][0]['author']['displayName'])->toBeNull();

    $author->forceFill(['status' => 'pending_deletion'])->save();
    AccountDeletionRequested::dispatch($author->id);
    expect(feedUlids($this->get('/bases')))->toBe([]);
});

it('caches only the first pages', function () {
    config(['bases.feed.per_page' => 1, 'bases.feed.cache_max_page' => 1]);
    foreach (range(1, 3) as $i) {
        feedBase(['published_at' => Date::now()->subMinutes($i)]);
    }
    $cursor = $this->get('/bases?sort=new')->viewData('page')['props']['nextCursor'];
    $second = loadMore('/bases?sort=new', $cursor)->json('props.cards.0.ulid');

    BaseLayout::query()->where('ulid', $second)->update(['title' => 'Renamed live']);

    expect(loadMore('/bases?sort=new', $cursor)->json('props.cards.0.title'))->toBe('Renamed live');
});

it('rate limits each feed page per visitor', function () {
    config(['bases.feed.requests_per_minute' => 2]);

    $this->get('/bases')->assertOk();
    $this->get('/')->assertOk();
    $this->get('/bases')->assertTooManyRequests();
    $this->actingAs(User::factory()->create())->get('/bases')->assertOk();
});
