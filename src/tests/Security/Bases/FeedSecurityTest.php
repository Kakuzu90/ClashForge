<?php

use App\Domain\Bases\Enums\BaseVisibility;
use App\Domain\Bases\Models\BaseLayout;
use App\Domain\Bases\Models\BaseTag;
use App\Models\User;

// P3-03, specs/11: feed input never reaches SQL as text, and no filter or cursor surfaces a base the
// feed must not list.

it('refuses injection attempts in every parameter as field errors', function (string $field, string $value) {
    BaseLayout::factory()->withMetrics()->create();

    $this->get('/bases?'.http_build_query([$field => $value]))->assertRedirect('/bases')->assertSessionHasErrors($field);
})->with([
    ['sort', 'id;DROP TABLE base_layouts'],
    ['sort', 'base_metrics.trending_score desc'],
    ['category', "war' OR '1'='1"],
    ['th', '16 OR 1=1'],
    ['min_likes', '1;DELETE FROM users'],
    ['cursor', "x' OR 1=1--"],
]);

it('lists no unlisted, private or sanctioned base under any filter that matches it', function (array $attributes) {
    $tag = BaseTag::factory()->create(['name' => 'secret-tag', 'slug' => 'secret-tag']);
    $base = BaseLayout::factory()->withMetrics(['likes_count' => 50])->create([...$attributes, 'th_level' => 16, 'has_video' => true]);
    $base->tags()->attach($tag);

    foreach (['', '?th=16', '?tag=secret-tag', '?video=1&min_likes=10', '?sort=new', '?sort=liked', '?sort=copied'] as $query) {
        expect(json_encode($this->get("/bases{$query}")->viewData('page')['props']['cards']))->not->toContain($base->ulid);
    }
})->with([
    'unlisted' => [['visibility' => BaseVisibility::Unlisted]],
    'private' => [['visibility' => BaseVisibility::Private]],
    'banned author' => [fn () => ['user_id' => User::factory()->banned()->create()->id]],
]);

it('does not cache a guest-capped page for members or the reverse', function () {
    config(['bases.feed.per_page' => 1, 'bases.feed.max_pages' => 1]);
    BaseLayout::factory()->withMetrics()->create();
    BaseLayout::factory()->withMetrics()->create();

    expect($this->get('/bases')->viewData('page')['props']['nextCursor'])->toBeNull()
        ->and($this->actingAs(User::factory()->create())->get('/bases')->viewData('page')['props']['nextCursor'])->not->toBeNull();
});

it('answers the home feed with 200 whatever junk the query string carries', function (string $query) {
    BaseLayout::factory()->withMetrics()->create();

    $this->get("/?{$query}")->assertOk();
})->with(['category=zzz', 'tag=%21%21%21', 'category[]=war', 'tag[]=x', 'min_likes[]=1', 'video[]=1']);

it('refuses a malformed home parameter as a field error, not a 500', function () {
    $this->get('/?th[]=16')->assertRedirect('/')->assertSessionHasErrors('th');
});
