<?php

use App\Domain\CocIntegration\Data\PlayerTag;
use App\Domain\CocIntegration\Enums\CocCircuitReason;
use App\Domain\CocIntegration\Enums\CocCircuitState;
use App\Domain\CocIntegration\Enums\CocFailureReason;
use App\Domain\CocIntegration\Enums\CocLookupStatus;
use App\Domain\CocIntegration\Enums\TokenVerificationStatus;
use App\Domain\CocIntegration\Exceptions\CocApiFailure;
use App\Domain\CocIntegration\Services\CocApiStatus;
use App\Domain\CocIntegration\Services\PlayerLookup;
use App\Domain\CocIntegration\Services\TokenVerifier;
use App\Domain\CocIntegration\Support\CircuitBreaker;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Http;
use Tests\Support\Coc\InteractsWithCoc;

// specs/09 §7 circuit breaker and the degradation contract; specs/23 §5 maintenance at peak.

uses(InteractsWithCoc::class);

beforeEach(function () {
    Date::setTestNow('2026-10-02 12:00:00');
    $this->useHttpCoc();
    $this->captureAppLog();
    config(['coc.rate.global_per_second' => 1000, 'coc.rate.global_per_minute' => 100000, 'coc.rate.per_key_per_second' => 1000]);
    $this->tag = PlayerTag::from('#2PQ8GRJC');
});

function fetchFresh(object $test): mixed
{
    return app(PlayerLookup::class)->find($test->tag, fresh: true);
}

function breakerState(): CocCircuitState
{
    return app(CocApiStatus::class)->state()->state;
}

it('opens after the configured run of consecutive failures and stops calling', function () {
    $threshold = (int) config('coc.circuit.consecutive_failures');
    Http::fake(['*' => Http::response('', 500)]);

    foreach (range(1, $threshold - 1) as $i) {
        fetchFresh($this);
    }
    expect(breakerState())->toBe(CocCircuitState::Closed);

    fetchFresh($this);
    expect(breakerState())->toBe(CocCircuitState::Open);

    $refused = fetchFresh($this);
    expect($refused->status)->toBe(CocLookupStatus::Unavailable)
        ->and($refused->failure)->toBe(CocFailureReason::CircuitOpen)
        ->and($refused->retryAfter)->toBe((int) config('coc.circuit.probe_interval'));
    Http::assertSentCount($threshold);

    $state = app(CocApiStatus::class)->state();
    expect($state->reason)->toBe(CocCircuitReason::Failures)
        ->and($state->openUntil?->getTimestamp())->toBe(Date::now()->addSeconds((int) config('coc.circuit.probe_interval'))->getTimestamp())
        ->and(app(CocApiStatus::class)->isAvailable())->toBeFalse()
        ->and(array_count_values($this->appLogMessages())['coc.circuit_opened'])->toBe(1);
});

it('resets the run on a success', function () {
    $threshold = (int) config('coc.circuit.consecutive_failures');
    $sequence = Http::fakeSequence('*');
    foreach (range(1, $threshold - 1) as $i) {
        $sequence->push('', 500);
    }
    $sequence->push($this->cocFixtureBody('players/2PQ8GRJC.json'));
    foreach (range(1, $threshold - 1) as $i) {
        $sequence->push('', 500);
    }

    foreach (range(1, 2 * $threshold - 1) as $i) {
        fetchFresh($this);
    }

    expect(breakerState())->toBe(CocCircuitState::Closed);
});

it('opens on an error rate above the line once there are enough samples', function () {
    config(['coc.circuit.consecutive_failures' => 1000, 'coc.circuit.min_samples' => 20, 'coc.circuit.error_rate' => 0.5]);
    $sequence = Http::fakeSequence('*');
    foreach (range(1, 9) as $i) {
        $sequence->push($this->cocFixtureBody('players/2PQ8GRJC.json'));
    }
    foreach (range(1, 11) as $i) {
        $sequence->push('', 502);
    }

    foreach (range(1, 19) as $i) {
        fetchFresh($this);
    }
    // 10 of 19 failed: above half, but below the minimum sample size.
    expect(breakerState())->toBe(CocCircuitState::Closed);

    fetchFresh($this);
    expect(breakerState())->toBe(CocCircuitState::Open);
});

it('forgets failures that left the window', function () {
    config(['coc.circuit.consecutive_failures' => 1000, 'coc.circuit.min_samples' => 20]);
    Http::fake(['*' => Http::response('', 500)]);

    foreach (range(1, 19) as $i) {
        fetchFresh($this);
    }

    $this->travel((int) config('coc.circuit.window') + (int) config('coc.circuit.bucket_seconds'))->seconds();
    fetchFresh($this);

    expect(breakerState())->toBe(CocCircuitState::Closed)
        ->and(app(CircuitBreaker::class)->window())->toBe(['ok' => 0, 'fail' => 1]);
});

it('does not count answers, throttling or refused keys as failures', function (int $status, string $file) {
    Http::fake(['*' => Http::response($this->cocFixtureBody($file), $status)]);

    foreach (range(1, (int) config('coc.circuit.consecutive_failures') + 5) as $i) {
        fetchFresh($this);
    }

    expect(breakerState())->toBe(CocCircuitState::Closed);
})->with([
    '404' => [404, 'responses/error-notFound.json'],
    '429' => [429, 'responses/error-throttled.json'],
    '403' => [403, 'responses/error-accessDenied.json'],
]);

it('lets one probe through after the open period: success closes', function () {
    $sequence = Http::fakeSequence('*');
    foreach (range(1, (int) config('coc.circuit.consecutive_failures')) as $i) {
        $sequence->push('', 500);
    }
    $sequence->push($this->cocFixtureBody('players/2PQ8GRJC.json'));

    foreach (range(1, (int) config('coc.circuit.consecutive_failures')) as $i) {
        fetchFresh($this);
    }

    $this->travel((int) config('coc.circuit.probe_interval'))->seconds();
    expect(breakerState())->toBe(CocCircuitState::HalfOpen)
        ->and(app(CocApiStatus::class)->isAvailable())->toBeTrue();

    expect(fetchFresh($this)->status)->toBe(CocLookupStatus::Found)
        ->and(breakerState())->toBe(CocCircuitState::Closed)
        ->and($this->appLogMessages())->toContain('coc.circuit_closed');
});

it('opens again for another interval when the probe fails', function () {
    Http::fake(['*' => Http::response('', 500)]);
    foreach (range(1, (int) config('coc.circuit.consecutive_failures')) as $i) {
        fetchFresh($this);
    }

    $this->travel((int) config('coc.circuit.probe_interval'))->seconds();
    fetchFresh($this);

    expect(breakerState())->toBe(CocCircuitState::Open)
        ->and(app(CocApiStatus::class)->state()->openUntil?->getTimestamp())->toBe(Date::now()->addSeconds((int) config('coc.circuit.probe_interval'))->getTimestamp());
});

it('lets only one caller probe at a time', function () {
    Http::fake(['*' => Http::response('', 500)]);
    foreach (range(1, (int) config('coc.circuit.consecutive_failures')) as $i) {
        fetchFresh($this);
    }
    $this->travel((int) config('coc.circuit.probe_interval'))->seconds();

    $breaker = app(CircuitBreaker::class);
    $breaker->allow();

    expect(fn () => $breaker->allow())->toThrow(CocApiFailure::class);
});

it('opens at once for maintenance, for as long as Retry-After says (owner decision 2026-10-02)', function () {
    Http::fake(['*' => Http::response($this->cocFixtureBody('responses/error-inMaintenance.json'), 503, ['Retry-After' => '300'])]);

    $first = fetchFresh($this);
    $second = fetchFresh($this);

    $state = app(CocApiStatus::class)->state();
    expect($first->failure)->toBe(CocFailureReason::Maintenance)
        ->and($second->failure)->toBe(CocFailureReason::Maintenance)
        ->and($state->reason)->toBe(CocCircuitReason::Maintenance)
        ->and($state->openUntil?->getTimestamp())->toBe(Date::now()->addSeconds(300)->getTimestamp());
    Http::assertSentCount(1);

    $this->travel(299)->seconds();
    expect(breakerState())->toBe(CocCircuitState::Open);
    $this->travel(1)->seconds();
    expect(breakerState())->toBe(CocCircuitState::HalfOpen);
});

it('probes every interval when maintenance has no stated end', function () {
    Http::fake(['*' => Http::response($this->cocFixtureBody('responses/error-inMaintenance.json'), 503)]);

    fetchFresh($this);

    expect(app(CocApiStatus::class)->state()->openUntil?->getTimestamp())
        ->toBe(Date::now()->addSeconds((int) config('coc.circuit.probe_interval'))->getTimestamp());
});

it('serves the last good answer while open, and refuses verification (degradation contract)', function () {
    Http::fakeSequence('*')
        ->push($this->cocFixtureBody('players/2PQ8GRJC.json'))
        ->push($this->cocFixtureBody('responses/error-inMaintenance.json'), 503);
    $fetchedAt = Date::now();
    app(PlayerLookup::class)->find($this->tag);

    $this->travel(10)->minutes();

    $during = app(PlayerLookup::class)->find($this->tag);
    $verify = app(TokenVerifier::class)->verify($this->tag, 'some-token');

    expect($during->status)->toBe(CocLookupStatus::Found)
        ->and($during->player?->stale)->toBeTrue()
        ->and($during->player?->fetchedAt?->equalTo($fetchedAt))->toBeTrue()
        ->and($verify->status)->toBe(TokenVerificationStatus::Unavailable)
        ->and($verify->failure)->toBe(CocFailureReason::Maintenance);
    Http::assertSentCount(2);
});

it('starts a clean window when it closes, so one error after recovery does not reopen it', function () {
    config(['coc.circuit.consecutive_failures' => 1000, 'coc.circuit.min_samples' => 20]);
    $sequence = Http::fakeSequence('*');
    foreach (range(1, 6) as $i) {
        $sequence->push($this->cocFixtureBody('players/2PQ8GRJC.json'));
    }
    foreach (range(1, 14) as $i) {
        $sequence->push('', 500);
    }
    $sequence->push($this->cocFixtureBody('players/2PQ8GRJC.json'))->push('', 500);

    foreach (range(1, 20) as $i) {
        fetchFresh($this);
    }
    expect(breakerState())->toBe(CocCircuitState::Open);

    $this->travel((int) config('coc.circuit.probe_interval'))->seconds();
    fetchFresh($this);
    expect(breakerState())->toBe(CocCircuitState::Closed)
        ->and(app(CircuitBreaker::class)->window())->toBe(['ok' => 0, 'fail' => 0]);

    fetchFresh($this);
    expect(breakerState())->toBe(CocCircuitState::Closed);
});

it('keeps counting a slow run of failures', function () {
    config(['coc.circuit.min_samples' => 1000]);
    Http::fake(['*' => Http::response('', 500)]);

    foreach (range(1, (int) config('coc.circuit.consecutive_failures')) as $i) {
        fetchFresh($this);
        $this->travel(20)->seconds();
    }

    expect(breakerState())->toBe(CocCircuitState::Open);
});

it('hands the probe back when it never reached a verdict', function () {
    $sequence = Http::fakeSequence('*');
    foreach (range(1, (int) config('coc.circuit.consecutive_failures')) as $i) {
        $sequence->push('', 500);
    }
    $sequence->push($this->cocFixtureBody('responses/error-throttled.json'), 429)
        ->push($this->cocFixtureBody('players/2PQ8GRJC.json'));

    foreach (range(1, (int) config('coc.circuit.consecutive_failures')) as $i) {
        fetchFresh($this);
    }
    $this->travel((int) config('coc.circuit.probe_interval'))->seconds();

    expect(fetchFresh($this)->failure)->toBe(CocFailureReason::Throttled)
        ->and(fetchFresh($this)->status)->toBe(CocLookupStatus::Found)
        ->and(breakerState())->toBe(CocCircuitState::Closed);
});

it('ignores late answers from calls admitted before it opened', function () {
    $breaker = app(CircuitBreaker::class);
    foreach (range(1, (int) config('coc.circuit.consecutive_failures')) as $i) {
        $breaker->recordFailure(new CocApiFailure(CocFailureReason::ServerError));
    }
    $until = app(CocApiStatus::class)->state()->openUntil;

    $this->travel(30)->seconds();
    $breaker->recordSuccess();
    $breaker->recordFailure(new CocApiFailure(CocFailureReason::Timeout));

    expect(breakerState())->toBe(CocCircuitState::Open)
        ->and(app(CocApiStatus::class)->state()->openUntil?->equalTo($until))->toBeTrue();
});

it('caps a maintenance Retry-After at max_open_seconds', function () {
    Http::fake(['*' => Http::response($this->cocFixtureBody('responses/error-inMaintenance.json'), 503, ['Retry-After' => '31536000'])]);

    fetchFresh($this);

    expect(app(CocApiStatus::class)->state()->openUntil?->getTimestamp())
        ->toBe(Date::now()->addSeconds((int) config('coc.circuit.max_open_seconds'))->getTimestamp());
});

it('sizes the window from bucket_seconds and window', function () {
    config(['coc.circuit.bucket_seconds' => 60, 'coc.circuit.window' => 120, 'coc.circuit.consecutive_failures' => 1000]);
    Http::fake(['*' => Http::response('', 500)]);
    Date::setTestNow('2026-10-02 12:00:00');

    fetchFresh($this);
    $this->travel(119)->seconds();
    expect(app(CircuitBreaker::class)->window()['fail'])->toBe(1);

    $this->travel(60)->seconds();
    expect(app(CircuitBreaker::class)->window()['fail'])->toBe(0);
});
