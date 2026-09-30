<?php

use App\Models\User;
use Illuminate\Auth\Events\Authenticated;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Log;

it('generates a request id, echoes it and puts it in the log context', function () {
    $response = $this->get('/');
    $id = $response->headers->get('X-Request-Id');

    expect($id)->toMatch('/^[0-9a-z]{26}$/')
        ->and(Context::get('request_id'))->toBe($id);
});

it('keeps a well-formed inbound request id', function () {
    $this->get('/', ['X-Request-Id' => 'edge-7f3a9c21-4b'])->assertHeader('X-Request-Id', 'edge-7f3a9c21-4b');
});

it('replaces a malformed inbound request id', function (string $inbound) {
    $id = $this->get('/', ['X-Request-Id' => $inbound])->headers->get('X-Request-Id');

    expect($id)->not->toBe($inbound)->toMatch('/^[0-9a-z]{26}$/');
})->with([
    'log injection' => "abc12345\nlevel=critical",
    'markup' => '<script>alert(1)</script>',
    'too short' => 'abc',
    'too long' => str_repeat('a', 65),
    'spaces' => 'abc def ghi',
]);

it('writes one summary line per request with route, status and duration', function () {
    Log::spy();

    $this->get('/')->assertOk();

    Log::shouldHaveReceived('info')->withArgs(fn (string $message, array $context = []) => $message === 'request'
        && $context['route'] === 'home'
        && $context['status'] === 200
        && $context['method'] === 'GET'
        && is_int($context['duration_ms']))->once();
});

it('leaves health probes out of the summary lines', function () {
    Log::spy();

    $this->get('/health');

    Log::shouldNotHaveReceived('info', fn (string $message) => $message === 'request');
});

it('adds the user id to the log context once a user is authenticated', function () {
    $user = User::factory()->create();

    event(new Authenticated('web', $user));

    expect(Context::get('user_id'))->toBe($user->id);
});

it('adds the matched route to the log context', function () {
    $this->get('/')->assertOk();

    expect(Context::get('route'))->toBe('home');
});
