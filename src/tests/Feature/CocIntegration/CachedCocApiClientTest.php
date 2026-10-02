<?php

use App\Domain\CocIntegration\Data\ClanTag;
use App\Domain\CocIntegration\Data\PlayerTag;
use App\Domain\CocIntegration\Enums\CocFailureReason;
use App\Domain\CocIntegration\Enums\CocLookupStatus;
use App\Domain\CocIntegration\Enums\CocPriority;
use App\Domain\CocIntegration\Models\CocApiRequest;
use App\Domain\CocIntegration\Services\ClanLookup;
use App\Domain\CocIntegration\Services\PlayerLookup;
use App\Domain\CocIntegration\Services\TokenVerifier;
use App\Domain\CocIntegration\Support\CocCacheKeys;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Http;
use Tests\Support\Coc\InteractsWithCoc;

// specs/09 §5, specs/21 §3: TTLs, negative cache, stale-while-error, background writes.

uses(InteractsWithCoc::class);

beforeEach(function () {
    Date::setTestNow('2026-10-02 12:00:00');
    $this->useHttpCoc();
    $this->tag = PlayerTag::from('#2PQ8GRJC');
});

function players(): PlayerLookup
{
    return app(PlayerLookup::class);
}

it('answers from the cache within player_ttl, logged as a cached hit', function () {
    Http::fake(['*' => Http::response($this->cocFixtureBody('players/2PQ8GRJC.json'))]);
    $first = players()->find($this->tag);

    $this->travel((int) config('coc.cache.player_ttl') - 1)->seconds();
    $second = players()->find($this->tag);

    expect($second->player?->name)->toBe('Fixture Chief')
        ->and($second->player?->stale)->toBeFalse()
        ->and($second->player?->fetchedAt?->equalTo($first->player?->fetchedAt))->toBeTrue()
        ->and($second->player?->rawPayload)->toBe($first->player?->rawPayload);
    Http::assertSentCount(1);

    expect(CocApiRequest::query()->orderBy('id')->pluck('was_cached')->all())->toBe([false, true]);
});

it('fetches again once player_ttl has passed', function () {
    Http::fake(['*' => Http::response($this->cocFixtureBody('players/2PQ8GRJC.json'))]);
    players()->find($this->tag);

    $this->travel((int) config('coc.cache.player_ttl') + 1)->seconds();
    players()->find($this->tag);

    Http::assertSentCount(2);
});

it('skips the read for background and fresh calls, and a background write lasts player_sync_ttl', function () {
    Http::fake(['*' => Http::response($this->cocFixtureBody('players/2PQ8GRJC.json'))]);

    players()->find($this->tag);
    players()->find($this->tag, fresh: true);
    players()->find($this->tag, CocPriority::Background);
    Http::assertSentCount(3);

    $this->travel((int) config('coc.cache.player_ttl') + 1)->seconds();
    players()->find($this->tag);
    Http::assertSentCount(3);

    $this->travel((int) config('coc.cache.player_sync_ttl'))->seconds();
    players()->find($this->tag);
    Http::assertSentCount(4);
});

it('caches a 404 for negative_ttl, per kind, and logs the cached miss', function () {
    Http::fake(['*' => Http::response($this->cocFixtureBody('responses/error-notFound.json'), 404)]);

    expect(players()->find($this->tag)->status)->toBe(CocLookupStatus::NotFound)
        ->and(players()->find($this->tag)->status)->toBe(CocLookupStatus::NotFound);
    Http::assertSentCount(1);
    expect(CocApiRequest::query()->where('was_cached', true)->value('status_code'))->toBe(404);

    // A clan with the same tag is a different lookup.
    app(ClanLookup::class)->find(ClanTag::from('#2PQ8GRJC'));
    Http::assertSentCount(2);

    $this->travel((int) config('coc.cache.negative_ttl') + 1)->seconds();
    players()->find($this->tag);
    Http::assertSentCount(3);
});

it('ignores the negative cache for background calls and clears it on success', function () {
    Http::fakeSequence('*')
        ->push($this->cocFixtureBody('responses/error-notFound.json'), 404)
        ->push($this->cocFixtureBody('players/2PQ8GRJC.json'));

    players()->find($this->tag);
    expect(players()->find($this->tag, CocPriority::Background)->status)->toBe(CocLookupStatus::Found)
        ->and(Cache::has(CocCacheKeys::notFound('player', $this->tag)))->toBeFalse()
        ->and(players()->find($this->tag)->status)->toBe(CocLookupStatus::Found);
    Http::assertSentCount(2);
});

it('serves the last good answer marked stale while the API fails, for stale_ttl', function () {
    Http::fakeSequence('*')
        ->push($this->cocFixtureBody('players/2PQ8GRJC.json'))
        ->push('', 500)
        ->push('', 500);
    $fetchedAt = Date::now();
    players()->find($this->tag);

    $this->travel((int) config('coc.cache.player_ttl') + 1)->seconds();
    $stale = players()->find($this->tag);

    expect($stale->status)->toBe(CocLookupStatus::Found)
        ->and($stale->player?->stale)->toBeTrue()
        ->and($stale->player?->fetchedAt?->equalTo($fetchedAt))->toBeTrue();

    $this->travel((int) config('coc.cache.stale_ttl'))->seconds();
    $gone = players()->find($this->tag);

    expect($gone->status)->toBe(CocLookupStatus::Unavailable)->and($gone->failure)->toBe(CocFailureReason::ServerError);
});

it('caches clans for clan_ttl', function () {
    Http::fake(['*' => Http::response($this->cocFixtureBody('clans/2Q8URJ9L.json'))]);
    $tag = ClanTag::from('#2Q8URJ9L');

    app(ClanLookup::class)->find($tag);
    $this->travel((int) config('coc.cache.clan_ttl') - 1)->seconds();
    app(ClanLookup::class)->find($tag);
    Http::assertSentCount(1);

    $this->travel(2)->seconds();
    app(ClanLookup::class)->find($tag);
    Http::assertSentCount(2);
});

it('never answers a token check from the cache', function () {
    Http::fake(['*' => Http::response($this->cocFixtureBody('responses/verifytoken-ok.json'))]);

    app(TokenVerifier::class)->verify($this->tag, 'token-one');
    app(TokenVerifier::class)->verify($this->tag, 'token-one');

    Http::assertSentCount(2);
});

it('stores plain arrays, so any cache driver can hold them (specs/21 §1)', function () {
    Http::fake(['*' => Http::response($this->cocFixtureBody('players/2PQ8GRJC.json'))]);
    players()->find($this->tag);

    $entry = Cache::get(CocCacheKeys::player($this->tag));
    expect($entry)->toBeArray()
        ->and(array_keys($entry))->toBe(['payload', 'fetched_at'])
        ->and($entry['fetched_at'])->toBe(Date::now()->getTimestamp())
        ->and(serialize($entry))->not->toContain('O:');
});

it('drops an entry it can no longer map and fetches again', function () {
    Http::fake(['*' => Http::response($this->cocFixtureBody('players/2PQ8GRJC.json'))]);
    Cache::put(CocCacheKeys::player($this->tag), ['payload' => ['tag' => '#2PQ8GRJC'], 'fetched_at' => Date::now()->getTimestamp()], 300);

    expect(players()->find($this->tag)->player?->name)->toBe('Fixture Chief');
    Http::assertSentCount(1);
});

it('gives background and fresh calls the failure, not the stale answer (specs/09 §6, §7)', function (CocPriority $priority, bool $fresh) {
    Http::fakeSequence('*')
        ->push($this->cocFixtureBody('players/2PQ8GRJC.json'))
        ->push('', 500);
    players()->find($this->tag);

    $result = players()->find($this->tag, $priority, $fresh);

    expect($result->status)->toBe(CocLookupStatus::Unavailable)
        ->and($result->failure)->toBe(CocFailureReason::ServerError)
        ->and($result->player)->toBeNull();
})->with([
    'background' => [CocPriority::Background, false],
    'manual refresh' => [CocPriority::Interactive, true],
]);
