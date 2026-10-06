<?php

use App\Domain\CocIntegration\Data\PlayerTag;
use App\Domain\CocIntegration\Enums\CocCircuitState;
use App\Domain\CocIntegration\Enums\CocFailureReason;
use App\Domain\CocIntegration\Enums\CocLookupStatus;
use App\Domain\CocIntegration\Enums\CocPriority;
use App\Domain\CocIntegration\Models\CocApiRequest;
use App\Domain\CocIntegration\Services\CocApiHealthReport;
use App\Domain\CocIntegration\Services\CocApiStatus;
use App\Domain\CocIntegration\Services\PlayerLookup;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Http;
use Tests\Support\Coc\InteractsWithCoc;

// P2-20: a manual refresh's own time limit (specs/09 §6). Running out of it is our deadline, not an
// API fault: the breaker and the API panel never count it (specs/09 §7).

uses(InteractsWithCoc::class);

beforeEach(function () {
    Date::setTestNow('2026-10-06 12:00:00');
    $this->useHttpCoc();
    config(['coc.rate.global_per_second' => 1000, 'coc.rate.global_per_minute' => 100000, 'coc.rate.per_key_per_second' => 1000]);
    $this->tag = PlayerTag::from('#2PQ8GRJC');
});

function manualLookup(object $test): mixed
{
    return app(PlayerLookup::class)->find($test->tag, CocPriority::Interactive, fresh: true, timeout: (int) config('coc.sync.manual_timeout'));
}

it('sends a manual refresh with its own time limit, and every other call with the usual ones', function () {
    $options = [];
    Http::fake(['*' => function (Request $request, array $sent) use (&$options) {
        $options[] = [$sent['connect_timeout'] ?? null, $sent['timeout'] ?? null];

        return Http::response($this->cocFixtureBody('players/2PQ8GRJC.json'));
    }]);

    expect(manualLookup($this)->status)->toBe(CocLookupStatus::Found);
    app(PlayerLookup::class)->find($this->tag, CocPriority::Background);

    $deadline = (int) config('coc.sync.manual_timeout');
    expect($options[0][0])->toBeLessThanOrEqual(min($deadline, (int) config('coc.timeouts.connect')))->toBeGreaterThan($deadline - 1)
        ->and($options[0][1])->toBeLessThanOrEqual($deadline)->toBeGreaterThan($deadline - 1)
        ->and($options[1])->toBe([(int) config('coc.timeouts.connect'), (int) config('coc.timeouts.total')]);
});

it('gives the key swap only what is left of the time limit', function () {
    $limits = [];
    Http::fake(['*' => function (Request $request, array $sent) use (&$limits) {
        $limits[] = $sent['timeout'];

        return count($limits) === 1
            ? Http::response($this->cocFixtureBody('responses/error-accessDenied.json'), 403)
            : Http::response($this->cocFixtureBody('players/2PQ8GRJC.json'));
    }]);

    expect(manualLookup($this)->status)->toBe(CocLookupStatus::Found)
        ->and($limits)->toHaveCount(2)
        ->and($limits[1])->toBeLessThan($limits[0]);
});

it('still counts a connection that fails for any other reason', function () {
    Http::fake(['*' => fn () => throw new ConnectionException('cURL error 7: Failed to connect to api.clashofclans.com')]);

    expect(manualLookup($this)->failure)->toBe(CocFailureReason::Timeout)
        ->and(app(CocApiHealthReport::class)->summary()->failures)->toBe(1);
});

it('reports a deadline, not a timeout, and the breaker never counts it', function () {
    Http::fake(['*' => fn () => throw new ConnectionException('cURL error 28: Operation timed out')]);

    foreach (range(1, (int) config('coc.circuit.consecutive_failures') + 1) as $ignored) {
        $result = manualLookup($this);
        expect($result->status)->toBe(CocLookupStatus::Unavailable)->and($result->failure)->toBe(CocFailureReason::Deadline);
    }

    expect(app(CocApiStatus::class)->state()->state)->toBe(CocCircuitState::Closed)
        ->and(CocApiRequest::query()->pluck('error_code')->unique()->all())->toBe([CocFailureReason::Deadline->value])
        ->and(app(CocApiHealthReport::class)->summary()->failures)->toBe(0);

    // A real timeout still counts.
    app(PlayerLookup::class)->find($this->tag, fresh: true);
    expect(app(CocApiHealthReport::class)->summary()->failures)->toBe(1);
});
