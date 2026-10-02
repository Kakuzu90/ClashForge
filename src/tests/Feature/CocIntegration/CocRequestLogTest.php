<?php

use App\Domain\CocIntegration\Data\PlayerTag;
use App\Domain\CocIntegration\Enums\CocLookupStatus;
use App\Domain\CocIntegration\Models\CocApiRequest;
use App\Domain\CocIntegration\Services\PlayerLookup;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\Support\Coc\InteractsWithCoc;

// specs/09 §4 and specs/07 `coc_api_requests`: one row per outbound call and per cache hit.

uses(InteractsWithCoc::class);

beforeEach(function () {
    Date::setTestNow('2026-10-02 12:00:00');
    $this->useHttpCoc();
    $this->tag = PlayerTag::from('#2PQ8GRJC');
});

it('writes one row per call with endpoint, tag, status and timing', function () {
    Http::fake(['*' => Http::response($this->cocFixtureBody('players/2PQ8GRJC.json'))]);

    app(PlayerLookup::class)->find($this->tag);

    $row = CocApiRequest::query()->sole();
    expect($row->endpoint)->toBe('players')
        ->and($row->tag)->toBe('#2PQ8GRJC')
        ->and($row->status_code)->toBe(200)
        ->and($row->duration_ms)->toBeGreaterThanOrEqual(0)
        ->and($row->was_cached)->toBeFalse()
        ->and($row->error_code)->toBeNull()
        ->and($row->created_at->equalTo(Date::now()))->toBeTrue();
});

it('writes both requests of a key swap', function () {
    Http::fakeSequence('*')
        ->push($this->cocFixtureBody('responses/error-invalidIp.json'), 403)
        ->push($this->cocFixtureBody('players/2PQ8GRJC.json'));

    app(PlayerLookup::class)->find($this->tag);

    expect(CocApiRequest::query()->orderBy('id')->get(['status_code', 'error_code'])->toArray())->toBe([
        ['status_code' => 403, 'error_code' => 'accessDenied.invalidIp'],
        ['status_code' => 200, 'error_code' => null],
    ]);
});

it('writes a timeout without a status', function () {
    Http::fake(fn () => throw new ConnectionException('cURL error 28'));

    app(PlayerLookup::class)->find($this->tag);

    $row = CocApiRequest::query()->sole();
    expect($row->status_code)->toBeNull()->and($row->error_code)->toBe('timeout');
});

it('writes the health probes', function () {
    Http::fake(['*' => Http::response($this->cocFixtureBody('responses/locations.json'))]);

    $this->artisan('coc:check-health')->assertSuccessful();

    expect(CocApiRequest::query()->where('endpoint', 'locations')->whereNull('tag')->count())->toBe(2);
});

it('never fails the call when the log cannot be written', function () {
    $this->captureAppLog();
    Schema::drop('coc_api_requests');
    Http::fake(['*' => Http::response($this->cocFixtureBody('players/2PQ8GRJC.json'))]);

    expect(app(PlayerLookup::class)->find($this->tag)->status)->toBe(CocLookupStatus::Found)
        ->and($this->appLogMessages())->toContain('coc.request_log_failed');
});
