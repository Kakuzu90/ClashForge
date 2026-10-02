<?php

use App\Domain\CocIntegration\Data\PlayerTag;
use App\Domain\CocIntegration\Enums\CocCircuitState;
use App\Domain\CocIntegration\Enums\CocFailureReason;
use App\Domain\CocIntegration\Enums\CocLookupStatus;
use App\Domain\CocIntegration\Enums\CocPriority;
use App\Domain\CocIntegration\Services\CocApiStatus;
use App\Domain\CocIntegration\Services\PlayerLookup;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Http;
use Tests\Support\Coc\InteractsWithCoc;

// specs/09 §4: self-imposed budgets, the interactive reservation, per-key caps and 429s.

uses(InteractsWithCoc::class);

beforeEach(function () {
    Date::setTestNow('2026-10-02 12:00:00');
    $this->useHttpCoc();
    config(['coc.rate.per_key_per_second' => 1000]);
    $this->tag = PlayerTag::from('#2PQ8GRJC');
});

function answerWithPlayer(object $test): void
{
    Http::fake(['*' => Http::response($test->cocFixtureBody('players/2PQ8GRJC.json'))]);
}

function lookup(object $test, CocPriority $priority = CocPriority::Interactive): mixed
{
    return app(PlayerLookup::class)->find($test->tag, $priority, fresh: true);
}

it('stops at the global per-second budget without calling, then recovers', function () {
    answerWithPlayer($this);
    $perSecond = (int) config('coc.rate.global_per_second');

    foreach (range(1, $perSecond) as $i) {
        expect(lookup($this)->player?->stale)->toBeFalse();
    }

    $over = app(PlayerLookup::class)->find(PlayerTag::from('#LQ2RJ9P0'));
    expect($over->status)->toBe(CocLookupStatus::Unavailable)
        ->and($over->failure)->toBe(CocFailureReason::Throttled)
        ->and($over->retryAfter)->toBeGreaterThanOrEqual(1);
    Http::assertSentCount($perSecond);

    $this->travel(1)->seconds();
    expect(lookup($this)->status)->toBe(CocLookupStatus::Found);
});

it('stops at the global per-minute budget', function () {
    config(['coc.rate.global_per_second' => 1000, 'coc.rate.global_per_minute' => 5]);
    answerWithPlayer($this);

    foreach (range(1, 5) as $i) {
        lookup($this);
    }

    expect(app(PlayerLookup::class)->find(PlayerTag::from('#LQ2RJ9P0'))->failure)->toBe(CocFailureReason::Throttled);

    $this->travel(61)->seconds();
    expect(lookup($this)->status)->toBe(CocLookupStatus::Found);
});

it('keeps the interactive share free when background work spends its own', function () {
    answerWithPlayer($this);
    $perSecond = (int) config('coc.rate.global_per_second');
    $background = (int) floor($perSecond * (1 - (float) config('coc.rate.interactive_share')));

    foreach (range(1, $background) as $i) {
        expect(lookup($this, CocPriority::Background)->status)->toBe(CocLookupStatus::Found);
    }

    expect(app(PlayerLookup::class)->find(PlayerTag::from('#LQ2RJ9P0'), CocPriority::Background)->failure)->toBe(CocFailureReason::Throttled);

    foreach (range(1, $perSecond - $background) as $i) {
        expect(lookup($this)->status)->toBe(CocLookupStatus::Found);
    }

    Http::assertSentCount($perSecond);
});

it('reports the background budget left this minute (specs/09 §6)', function () {
    answerWithPlayer($this);
    $minute = (int) floor((int) config('coc.rate.global_per_minute') * (1 - (float) config('coc.rate.interactive_share')));

    expect(app(CocApiStatus::class)->backgroundBudgetRemaining())->toBe($minute);

    lookup($this, CocPriority::Background);
    lookup($this);

    expect(app(CocApiStatus::class)->backgroundBudgetRemaining())->toBe($minute - 1);
});

it('moves to the next key when one is at its own budget, and throttles when all are', function () {
    config(['coc.rate.per_key_per_second' => 1]);
    answerWithPlayer($this);

    lookup($this);
    lookup($this);
    $third = app(PlayerLookup::class)->find(PlayerTag::from('#LQ2RJ9P0'));

    $keys = Http::recorded()->map(fn (array $pair): string => $pair[0]->header('Authorization')[0])->unique();
    expect($keys)->toHaveCount(2)
        ->and($third->failure)->toBe(CocFailureReason::Throttled);
    Http::assertSentCount(2);

    $this->travel(1)->seconds();
    expect(lookup($this)->status)->toBe(CocLookupStatus::Found);
});

it('spends no budget on a cache hit (decorator order, specs/09 §1)', function () {
    config(['coc.rate.global_per_second' => 1]);
    answerWithPlayer($this);

    $first = app(PlayerLookup::class)->find($this->tag);
    $second = app(PlayerLookup::class)->find($this->tag);

    expect($first->status)->toBe(CocLookupStatus::Found)->and($second->status)->toBe(CocLookupStatus::Found);
    Http::assertSentCount(1);
});

it('treats a 429 as normal: retry-after passed on, breaker untouched', function () {
    config(['coc.rate.global_per_second' => 1000]);
    Http::fake(['*' => Http::response($this->cocFixtureBody('responses/error-throttled.json'), 429, ['Retry-After' => '7'])]);

    foreach (range(1, (int) config('coc.circuit.consecutive_failures') + 2) as $i) {
        $result = lookup($this);
    }

    expect($result->failure)->toBe(CocFailureReason::Throttled)
        ->and($result->retryAfter)->toBe(7)
        ->and(app(CocApiStatus::class)->state()->state)->toBe(CocCircuitState::Closed);
    Http::assertSentCount((int) config('coc.circuit.consecutive_failures') + 2);
});
