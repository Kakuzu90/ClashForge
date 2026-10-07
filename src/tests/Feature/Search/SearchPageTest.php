<?php

use App\Domain\PlayerAccounts\Models\CocAccount;
use App\Domain\Users\Enums\ProfileVisibility;
use App\Models\User;
use Illuminate\Support\Facades\RateLimiter;
use Inertia\Testing\AssertableInertia as Assert;

// P3-05: `/search` without full-text matching, so these run on SQLite and Postgres alike: the
// empty page, validation, the tag short-circuit (FR-SEARCH-2) and the limiter.

it('renders an empty search with nothing searched, and never asks to be indexed', function () {
    $this->get('/search')->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Search/Index')
        ->where('q', '')
        ->where('type', 'all')
        ->where('searched', [])
        ->where('bases', [])
        ->where('players', [])
        ->where('accounts', [])
        ->where('tag', null)
        ->where('filters.sort', 'relevance')
        ->where('options.sorts.0.value', 'relevance')
        ->where('meta.title', 'Search'))
        ->assertSee('<meta name="robots" content="noindex', false);
});

it('rejects one-character, oversized and malformed searches as field errors', function (array $query, string $field) {
    $this->get('/search?'.http_build_query($query))->assertRedirect()->assertSessionHasErrors($field);
})->with([
    'one character' => [['q' => 'a'], 'q'],
    'too long' => [['q' => str_repeat('ring ', 30)], 'q'],
    'unknown type' => [['q' => 'ring', 'type' => 'clans'], 'type'],
    'unknown sort' => [['q' => 'ring', 'type' => 'bases', 'sort' => 'oldest'], 'sort'],
    'bad cursor' => [['q' => 'ring', 'type' => 'bases', 'cursor' => 'not-a-cursor'], 'cursor'],
    'a cursor on the grouped search' => [['q' => 'ring', 'cursor' => 'not-a-cursor'], 'cursor'],
]);

it('keeps the text when only a filter was wrong', function () {
    $this->get('/search?q=ring&type=bases&sort=oldest')->assertRedirect('/search?q=ring');
    $this->get('/search?q=a')->assertRedirect('/search');
});

it('sends an exact player tag straight to the account page, typed in any case', function (string $q) {
    $account = CocAccount::factory()->verified()->forTag('#2PPQ')->create();

    $this->get('/search?q='.urlencode($q))->assertRedirect("/accounts/{$account->ulid}");
})->with(['#2PPQ', '#2ppq', '2PPQ']);

it('answers a tag it may not show exactly like an unknown tag', function (Closure $make) {
    $make();

    $this->get('/search?q='.urlencode('#2PPQ'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Search/Index')
        ->where('tag', '#2PPQ')
        ->where('searched', []));
})->with([
    'unknown' => [fn () => null],
    'unverified' => [fn () => CocAccount::factory()->forTag('#2PPQ')->create()],
    'released' => [fn () => CocAccount::factory()->verified()->released()->forTag('#2PPQ')->create()],
    'accounts hidden' => [fn () => CocAccount::factory()->verified()->forTag('#2PPQ')->for(User::factory()->withPrivacy(['show_coc_accounts' => false]))->create()],
    'search turned off' => [fn () => CocAccount::factory()->verified()->forTag('#2PPQ')->for(User::factory()->withPrivacy(['searchable' => false]))->create()],
    'private profile' => [fn () => CocAccount::factory()->verified()->forTag('#2PPQ')->for(User::factory()->withPrivacy(['profile_visibility' => ProfileVisibility::Private]))->create()],
    'members profile, guest' => [fn () => CocAccount::factory()->verified()->forTag('#2PPQ')->for(User::factory()->withPrivacy(['profile_visibility' => ProfileVisibility::Members]))->create()],
    'banned owner' => [fn () => CocAccount::factory()->verified()->forTag('#2PPQ')->for(User::factory()->banned())->create()],
    'suspended owner' => [fn () => CocAccount::factory()->verified()->forTag('#2PPQ')->for(User::factory()->suspended())->create()],
]);

it('finds a members-only owner\'s tag for a signed-in viewer', function () {
    $account = CocAccount::factory()->verified()->forTag('#2PPQ')->for(User::factory()->withPrivacy(['profile_visibility' => ProfileVisibility::Members]))->create();

    $this->actingAs(User::factory()->create())->get('/search?q=%232PPQ')->assertRedirect("/accounts/{$account->ulid}");
});

it('limits searches per IP for guests and per user when signed in', function () {
    config(['platform.search.rate_limits.per_ip' => 2, 'platform.search.rate_limits.per_user' => 3]);
    RateLimiter::clear('search');

    $this->get('/search')->assertOk();
    $this->get('/search')->assertOk();
    $this->get('/search')->assertTooManyRequests();

    $user = User::factory()->create();
    foreach (range(1, 3) as $i) {
        $this->actingAs($user)->get('/search')->assertOk();
    }
    $this->actingAs($user)->get('/search')->assertTooManyRequests();
});

it('keeps every search limit and weight in config', function () {
    expect(config('platform.search'))->toMatchArray([
        'per_page' => 20,
        'group_size' => ['bases' => 6, 'players' => 5, 'accounts' => 5],
        'min_term' => 2,
        'max_term' => 100,
        'max_pages' => 100,
        'cache_ttl' => 60,
        'facet_cache_ttl' => 60,
        'snippet_length' => 140,
        'rate_limits' => ['per_ip' => 60, 'per_user' => 120],
    ])->and(config('platform.search.ranking'))->toBe([
        'text' => 0.5,
        'trending' => 0.3,
        'recency' => 0.1,
        'author_quality' => 0.1,
        'trending_pivot' => 1.0,
        'recency_half_life_days' => 14,
    ]);
});
