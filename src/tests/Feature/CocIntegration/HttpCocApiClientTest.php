<?php

use App\Domain\CocIntegration\Data\ClanTag;
use App\Domain\CocIntegration\Data\PlayerTag;
use App\Domain\CocIntegration\Enums\CocFailureReason;
use App\Domain\CocIntegration\Enums\CocLookupStatus;
use App\Domain\CocIntegration\Enums\TokenVerificationStatus;
use App\Domain\CocIntegration\Services\ClanLookup;
use App\Domain\CocIntegration\Services\CocKeyPool;
use App\Domain\CocIntegration\Services\PlayerLookup;
use App\Domain\CocIntegration\Services\TokenVerifier;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Http;
use Tests\Support\Coc\InteractsWithCoc;

// specs/09 §10 integration layer: every row of the §7 failure table through Http::fake.

uses(InteractsWithCoc::class);

beforeEach(function () {
    Date::setTestNow('2026-10-02 12:00:00');
    $this->useHttpCoc();
    $this->captureAppLog();
    $this->tag = PlayerTag::from('#2PQ8GRJC');
});

function cocResponse(object $test, string $file, int $status = 200, array $headers = []): PromiseInterface
{
    return Http::response($test->cocFixtureBody($file), $status, $headers);
}

it('fetches and maps a player with a key from the pool (FR-COC-3)', function () {
    Http::fake(['api.clashofclans.com/v1/players/*' => cocResponse($this, 'players/2PQ8GRJC.json')]);

    $result = app(PlayerLookup::class)->find($this->tag);

    expect($result->status)->toBe(CocLookupStatus::Found)
        ->and($result->player?->name)->toBe('Fixture Chief');

    Http::assertSent(fn (Request $r): bool => $r->method() === 'GET'
        && $r->url() === config('coc.base_url').'/players/%232PQ8GRJC'
        && $r->hasHeader('Accept', 'application/json')
        && in_array($r->header('Authorization')[0], ['Bearer test-key-one-secret', 'Bearer test-key-two-secret'], true));
});

it('fetches and maps a clan', function () {
    Http::fake(['api.clashofclans.com/v1/clans/*' => cocResponse($this, 'clans/2Q8URJ9L.json')]);

    $result = app(ClanLookup::class)->find(ClanTag::from('#2Q8URJ9L'));

    expect($result->status)->toBe(CocLookupStatus::Found)->and($result->clan?->memberCount)->toBe(2);
    Http::assertSent(fn (Request $r): bool => $r->url() === config('coc.base_url').'/clans/%232Q8URJ9L');
});

it('spreads calls across the keys round-robin (specs/09 §3)', function () {
    Http::fake(['*' => cocResponse($this, 'players/2PQ8GRJC.json')]);

    app(PlayerLookup::class)->find($this->tag);
    app(PlayerLookup::class)->find($this->tag);

    $keys = Http::recorded()->map(fn (array $pair): string => $pair[0]->header('Authorization')[0])->all();
    expect($keys)->toHaveCount(2)->and(array_unique($keys))->toHaveCount(2);
});

it('verifies a token with a POST and reports ok (FR-COC-5)', function () {
    Http::fake(['*/verifytoken' => cocResponse($this, 'responses/verifytoken-ok.json')]);

    $result = app(TokenVerifier::class)->verify($this->tag, 'in-game-token-1');

    expect($result->status)->toBe(TokenVerificationStatus::Ok)
        ->and($result->verifiedAt?->equalTo(Date::now()))->toBeTrue();
    Http::assertSent(fn (Request $r): bool => $r->method() === 'POST'
        && $r->url() === config('coc.base_url').'/players/%232PQ8GRJC/verifytoken'
        && $r->data() === ['token' => 'in-game-token-1']);
});

it('reports an invalid token without a verification time', function () {
    Http::fake(['*/verifytoken' => cocResponse($this, 'responses/verifytoken-invalid.json')]);

    $result = app(TokenVerifier::class)->verify($this->tag, 'stale-token');

    expect($result->status)->toBe(TokenVerificationStatus::Invalid)->and($result->verifiedAt)->toBeNull();
});

it('treats a blank token as invalid without calling the API', function () {
    Http::fake();

    expect(app(TokenVerifier::class)->verify($this->tag, "  \n")->status)->toBe(TokenVerificationStatus::Invalid);
    Http::assertNothingSent();
});

it('swaps a refused key for another and retries once (specs/09 §7)', function (string $file, string $level) {
    Http::fakeSequence('*')
        ->push($this->cocFixtureBody($file), 403)
        ->push($this->cocFixtureBody('players/2PQ8GRJC.json'), 200);

    $result = app(PlayerLookup::class)->find($this->tag);

    expect($result->status)->toBe(CocLookupStatus::Found);

    $used = Http::recorded()->map(fn (array $pair): string => $pair[0]->header('Authorization')[0])->all();
    expect($used)->toHaveCount(2)->and($used[0])->not->toBe($used[1]);

    $status = app(CocKeyPool::class)->status();
    expect($status->healthy)->toBe(1)
        ->and(collect($this->appLogRecords())->firstWhere('message', 'coc.key_unhealthy')['level'] ?? null)->toBe($level);
})->with([
    'revoked key' => ['responses/error-accessDenied.json', 'error'],
    'egress IP not on the key' => ['responses/error-invalidIp.json', 'critical'],
]);

it('gives up after the second refused key and stops calling (specs/09 §7)', function () {
    Http::fake(['*' => cocResponse($this, 'responses/error-invalidIp.json', 403)]);

    $first = app(PlayerLookup::class)->find($this->tag);
    $second = app(PlayerLookup::class)->find($this->tag);

    expect($first->status)->toBe(CocLookupStatus::Unavailable)
        ->and($first->failure)->toBe(CocFailureReason::NoHealthyKey)
        ->and($second->failure)->toBe(CocFailureReason::NoHealthyKey)
        ->and(app(CocKeyPool::class)->status()->healthy)->toBe(0)
        ->and($this->appLogMessages())->toContain('coc.keys_all_unhealthy');
    Http::assertSentCount(2);
});

it('reports no healthy key when none is configured, without a request', function () {
    $this->useHttpCoc([]);
    Http::fake();

    expect(app(PlayerLookup::class)->find($this->tag)->failure)->toBe(CocFailureReason::NoHealthyKey);
    Http::assertNothingSent();
});

it('trusts the API on an unknown tag and logs the mismatch (specs/23 §2)', function () {
    Http::fake(['*' => cocResponse($this, 'responses/error-notFound.json', 404)]);

    $player = app(PlayerLookup::class)->find($this->tag);
    $clan = app(ClanLookup::class)->find(ClanTag::from('#2Q8URJ9L'));
    $token = app(TokenVerifier::class)->verify($this->tag, 'any-token');

    expect($player->status)->toBe(CocLookupStatus::NotFound)
        ->and($player->player)->toBeNull()
        ->and($clan->status)->toBe(CocLookupStatus::NotFound)
        ->and($token->status)->toBe(TokenVerificationStatus::NotFound);

    $logged = collect($this->appLogRecords())->where('message', 'coc.tag_not_found')->values();
    expect($logged)->toHaveCount(2)
        ->and($logged[0]['context'])->toBe(['kind' => 'player', 'tag' => '#2PQ8GRJC']);
});

it('maps API failures to an unavailable result, never an exception (specs/09 §7)', function (Closure $response, CocFailureReason $reason, ?int $retryAfter) {
    Http::fake(['*' => $response($this)]);

    $result = app(PlayerLookup::class)->find($this->tag);
    $token = app(TokenVerifier::class)->verify($this->tag, 'any-token');

    expect($result->status)->toBe(CocLookupStatus::Unavailable)
        ->and($result->failure)->toBe($reason)
        ->and($result->retryAfter)->toBe($retryAfter)
        ->and($token->status)->toBe(TokenVerificationStatus::Unavailable)
        ->and($token->failure)->toBe($reason)
        ->and(app(CocKeyPool::class)->status()->healthy)->toBe(2);
})->with([
    'throttled with Retry-After' => [fn ($t) => cocResponse($t, 'responses/error-throttled.json', 429, ['Retry-After' => '30']), CocFailureReason::Throttled, 30],
    'throttled without Retry-After' => [fn ($t) => cocResponse($t, 'responses/error-throttled.json', 429), CocFailureReason::Throttled, null],
    'maintenance' => [fn ($t) => cocResponse($t, 'responses/error-inMaintenance.json', 503), CocFailureReason::Maintenance, null],
    '503 without a reason' => [fn ($t) => Http::response('', 503), CocFailureReason::ServerError, null],
    '500' => [fn ($t) => Http::response('{"reason":"unknownException"}', 500), CocFailureReason::ServerError, null],
    '502' => [fn ($t) => Http::response('<html>bad gateway</html>', 502), CocFailureReason::ServerError, null],
    '504' => [fn ($t) => Http::response('', 504), CocFailureReason::ServerError, null],
    'timeout' => [fn ($t) => fn () => throw new ConnectionException('cURL error 28: Operation timed out'), CocFailureReason::Timeout, null],
]);

it('refuses a malformed 200 and logs its body, cut short (specs/23 §5)', function () {
    config(['coc.log.malformed_body_bytes' => 40]);
    Http::fake(['*' => cocResponse($this, 'responses/player-wrong-type.json')]);

    $result = app(PlayerLookup::class)->find($this->tag);

    expect($result->status)->toBe(CocLookupStatus::Unavailable)
        ->and($result->failure)->toBe(CocFailureReason::Malformed)
        ->and($result->player)->toBeNull();

    $log = collect($this->appLogRecords())->firstWhere('message', 'coc.malformed_response');
    expect($log['context']['endpoint'])->toBe('players')
        ->and($log['context']['problem'])->toContain('players.townHallLevel is not an integer')
        ->and(strlen($log['context']['body']))->toBe(40);
});

it('refuses an empty 200 body', function () {
    Http::fake(['*' => Http::response('', 200)]);

    expect(app(PlayerLookup::class)->find($this->tag)->failure)->toBe(CocFailureReason::Malformed);
});

it('uses the configured base URL', function () {
    config(['coc.base_url' => 'https://coc-proxy.test/v1']);
    Http::fake(['coc-proxy.test/*' => cocResponse($this, 'players/2PQ8GRJC.json')]);

    expect(app(PlayerLookup::class)->find($this->tag)->status)->toBe(CocLookupStatus::Found);
    Http::assertSent(fn (Request $r): bool => str_starts_with($r->url(), 'https://coc-proxy.test/v1/players/'));
});
