<?php

use App\Domain\CocIntegration\Contracts\CocApiClient;
use App\Domain\CocIntegration\Data\PlayerTag;
use App\Domain\CocIntegration\Services\CocKeyPool;
use App\Domain\CocIntegration\Services\PlayerLookup;
use App\Domain\CocIntegration\Services\TokenVerifier;
use App\Domain\CocIntegration\Support\CocApiKey;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\Support\Coc\InteractsWithCoc;

// specs/09 §3, §9 and specs/11 §2–3: API keys and in-game tokens never reach a log, an exception,
// a cache value, a queued payload or a result object.

uses(InteractsWithCoc::class);

const KEY_ONE = 'sk-live-coc-key-one-0123456789';
const KEY_TWO = 'sk-live-coc-key-two-9876543210';
const IN_GAME = 'zx9yq2ab';

beforeEach(function () {
    $this->useHttpCoc([KEY_ONE, KEY_TWO]);
    $this->captureAppLog();
    $this->tag = PlayerTag::from('#2PQ8GRJC');
});

/**
 * Every value in the array cache store, serialised, so a secret anywhere in it is found.
 */
function cachedText(): string
{
    $store = Cache::getStore();
    $storage = (new ReflectionProperty($store, 'storage'))->getValue($store);

    return serialize($storage);
}

it('keeps the keys out of logs, exceptions and the cache on every failure path', function (Closure $response) {
    Http::fake(['*' => $response($this)]);

    $result = app(PlayerLookup::class)->find($this->tag);
    $this->artisan('coc:check-health');

    $everything = $this->appLogText().cachedText().serialize($result).json_encode(app(CocKeyPool::class)->status());
    expect($everything)->not->toContain(KEY_ONE)->not->toContain(KEY_TWO);
})->with([
    'refused keys' => [fn ($t) => Http::response($t->cocFixtureBody('responses/error-invalidIp.json'), 403)],
    'malformed body' => [fn ($t) => Http::response('{"tag":"#2PQ8GRJC","name":5}')],
    'server error' => [fn ($t) => Http::response('', 500)],
    'timeout' => [fn ($t) => fn () => throw new ConnectionException('cURL error 28 for https://api.clashofclans.com/v1/players/%232PQ8GRJC')],
]);

it('never logs the in-game token, even when the verifytoken body is malformed', function () {
    // The real response echoes the token; a shape change must not put it in the logs.
    Http::fake(['*' => Http::response('{"tag":"#2PQ8GRJC","token":"'.IN_GAME.'","status":"maybe"}')]);

    $result = app(TokenVerifier::class)->verify($this->tag, IN_GAME);

    expect($this->appLogMessages())->toContain('coc.malformed_response')
        ->and($this->appLogText())->not->toContain(IN_GAME)
        ->and(serialize($result))->not->toContain(IN_GAME)
        ->and(cachedText())->not->toContain(IN_GAME);
});

it('keeps the in-game token out of the result on success', function () {
    Http::fake(['*' => Http::response('{"tag":"#2PQ8GRJC","token":"'.IN_GAME.'","status":"ok"}')]);

    $result = app(TokenVerifier::class)->verify($this->tag, IN_GAME);

    expect(serialize($result))->not->toContain(IN_GAME)
        ->and(json_encode($result))->not->toContain(IN_GAME)
        ->and($this->appLogText())->not->toContain(IN_GAME);
});

it('hides the token from var_dump and refuses to serialise a key', function () {
    $key = CocApiKey::fromToken(KEY_ONE);

    ob_start();
    var_dump($key);
    $dumped = (string) ob_get_clean();

    expect($dumped)->not->toContain(KEY_ONE)->toContain($key->id)
        ->and(fn () => serialize($key))->toThrow(LogicException::class);
});

it('keeps the token out of a stack trace', function () {
    try {
        (function (#[SensitiveParameter] string $token) {
            throw new RuntimeException('boom');
        })(IN_GAME);
    } catch (RuntimeException $e) {
        expect($e->getTraceAsString())->not->toContain(IN_GAME);
    }

    try {
        CocApiKey::fromToken(KEY_ONE)->__serialize();
    } catch (LogicException $e) {
        expect($e->getTraceAsString())->not->toContain(KEY_ONE);
    }
});

it('refuses the fake driver in production', function () {
    config(['coc.driver' => 'fake']);
    $this->app['env'] = 'production';

    try {
        expect(fn () => app(CocApiClient::class))->toThrow(RuntimeException::class, 'not allowed in production');
    } finally {
        $this->app['env'] = 'testing';
    }
});

it('refuses an unknown driver', function () {
    config(['coc.driver' => 'mock']);

    expect(fn () => app(CocApiClient::class))->toThrow(InvalidArgumentException::class);
});
