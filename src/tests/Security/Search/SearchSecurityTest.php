<?php

use App\Domain\Bases\Models\BaseLayout;
use App\Domain\Users\Enums\ProfileVisibility;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

// P3-05: search input is bound, never interpolated (specs/11 §2), and visibility holds in the
// query (FR-SEARCH-6).

it('turns NUL bytes and invalid UTF-8 into a field error, not a 500', function (string $q) {
    $this->get('/search?q='.rawurlencode($q))->assertRedirect()->assertSessionHasErrors('q');
})->with([
    'NUL byte' => ["ring\0box"],
    'invalid UTF-8' => ["ring\xC3\x28"],
]);

it('treats SQL and tsquery syntax as plain text', function (string $q) {
    if (DB::getDriverName() !== 'pgsql') {
        $this->markTestSkipped('Full-text search needs Postgres.');
    }

    BaseLayout::factory()->withMetrics()->create(['title' => 'Ring']);

    $this->get('/search?q='.rawurlencode($q))->assertOk()->assertInertia(fn (Assert $page) => $page->component('Search/Index'));
    expect(BaseLayout::query()->count())->toBe(1);
})->with([
    "ring'; DROP TABLE base_layouts; --",
    'ring & | ! ( ) : *',
    "ring' OR 1=1 --",
    '"unclosed phrase',
    'ring --',
    'ring \\\\ % _',
]);

it('never shows a members-only or private profile to a guest', function () {
    if (DB::getDriverName() !== 'pgsql') {
        $this->markTestSkipped('Full-text search needs Postgres.');
    }

    User::factory()->withPrivacy(['profile_visibility' => ProfileVisibility::Members])->create(['username' => 'echo_members']);
    User::factory()->withPrivacy(['profile_visibility' => ProfileVisibility::Private])->create(['username' => 'echo_private']);

    $this->get('/search?q=echo&type=players')->assertOk()->assertInertia(fn (Assert $page) => $page->where('players', []));
});

it('refuses a search made only of exclusions, which would scan every row', function (string $q) {
    if (DB::getDriverName() !== 'pgsql') {
        $this->markTestSkipped('Full-text search needs Postgres.');
    }

    $queries = [];
    DB::listen(function ($query) use (&$queries): void {
        $queries[] = $query->sql;
    });

    $this->get('/search?q='.rawurlencode($q))->assertRedirect('/search')->assertSessionHasErrors(['q' => 'Add a word to search for, not only words to leave out.']);
    expect(array_filter($queries, fn (string $sql): bool => str_contains($sql, '@@')))->toBe([]);
})->with(['-zz', '-a -b', 'ring or -zz', '--ring', '!!', '\\\\ % _']);

it('drops exclusion-only leftovers but keeps the filters the text named', function () {
    if (DB::getDriverName() !== 'pgsql') {
        $this->markTestSkipped('Full-text search needs Postgres.');
    }

    BaseLayout::factory()->withMetrics()->create(['title' => 'Ring', 'th_level' => 16]);
    BaseLayout::factory()->withMetrics()->create(['title' => 'Box', 'th_level' => 15]);

    $this->get('/search?q='.rawurlencode('TH16 -zz').'&type=bases')->assertOk()->assertInertia(fn (Assert $page) => $page
        ->has('bases', 1)
        ->where('searched', ['bases']));
});
