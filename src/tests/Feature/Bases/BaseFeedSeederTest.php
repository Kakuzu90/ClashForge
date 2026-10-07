<?php

use App\Domain\Bases\Models\BaseLayout;
use App\Domain\Bases\Models\BaseMetric;
use App\Models\User;
use Database\Seeders\BaseFeedSeeder;

// P3-03: local feed data.

it('seeds authors, bases and scores once, and the feed lists them', function () {
    User::factory()->create(['username' => 'test_user']);

    $this->seed(BaseFeedSeeder::class);
    $this->seed(BaseFeedSeeder::class);

    expect(BaseLayout::query()->count())->toBe(60)
        ->and(BaseMetric::query()->where('trending_score', '>', 0)->count())->toBeGreaterThan(0)
        ->and(User::query()->where('username', 'like', 'seed_author_%')->count())->toBe(6);

    $this->get('/bases')->assertOk()->assertInertia(fn ($page) => $page->has('cards', (int) config('bases.feed.per_page'))->whereNot('nextCursor', null));
    $this->actingAs(User::query()->where('username', 'test_user')->sole())->get('/')
        ->assertInertia(fn ($page) => $page->where('thFromAccount', true)->where('filters.thMin', 15));
});
