<?php

use App\Support\Observability\SentryEventScrubber;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Context;
use Sentry\Event;
use Sentry\EventHint;
use Sentry\ExceptionDataBag;
use Sentry\Frame;
use Sentry\Stacktrace;
use Sentry\UserDataBag;

it('drops cookies, bodies, auth headers, query strings and personal user fields', function () {
    Context::add('request_id', '01j00000000000000000000000');
    $event = Event::createEvent();
    $event->setRequest([
        'url' => 'https://clash.test/reset-password/abc?email=player@example.com&signature=xyz',
        'method' => 'POST',
        'query_string' => 'token=secret',
        'cookies' => ['clash_session' => 'abc'],
        'data' => ['filename' => 'me.jpg'],
        'headers' => ['Authorization' => 'Bearer x', 'Cookie' => 'a=b', 'X-XSRF-TOKEN' => 'y', 'X-Forwarded-For' => '203.0.113.9', 'CF-Connecting-IP' => '203.0.113.9', 'X-Real-IP' => '203.0.113.9', 'True-Client-IP' => '203.0.113.9', 'Forwarded' => 'for=203.0.113.9', 'User-Agent' => 'test'],
    ]);
    $event->setUser(UserDataBag::createFromArray(['id' => 7, 'email' => 'player@example.com', 'ip_address' => '203.0.113.9']));

    $scrubbed = SentryEventScrubber::scrub($event);
    $request = $scrubbed->getRequest();

    expect($request)->not->toHaveKeys(['cookies', 'data'])
        ->and($request['url'])->toBe('https://clash.test/reset-password/[filtered]')
        ->and($request['query_string'])->toBe('[filtered]')
        ->and(array_keys($request['headers']))->toBe(['User-Agent'])
        ->and($scrubbed->getUser()?->getId())->toBe(7)
        ->and($scrubbed->getUser()?->getEmail())->toBeNull()
        ->and($scrubbed->getUser()?->getIpAddress())->toBeNull()
        ->and($scrubbed->getTags())->toMatchArray(['request_id' => '01j00000000000000000000000']);
});

it('drops frame arguments and query bindings from exceptions', function () {
    $query = new QueryException('pgsql', 'select * from users where email = ?', ['player@example.com'], new PDOException('SQLSTATE[23505]: duplicate'));
    $event = Event::createEvent();
    $frame = new Frame('attempt', 'SessionGuard.php', 429, null, null, ['credentials' => ['password' => 'hunter2']]);
    $event->setExceptions([new ExceptionDataBag($query, new Stacktrace([$frame]))]);

    $scrubbed = SentryEventScrubber::scrub($event, EventHint::fromArray(['exception' => $query]));
    $exception = $scrubbed->getExceptions()[0];

    expect($exception->getStacktrace()?->getFrames()[0]->getVars())->toBe([])
        ->and($exception->getValue())->not->toContain('player@example.com')
        ->and($exception->getValue())->toContain('select * from users where email = ?');
});

it('is wired as before_send with release and environment from env', function () {
    expect(config('sentry.before_send'))->toBe([SentryEventScrubber::class, 'scrub'])
        ->and(config('sentry.before_send_transaction'))->toBe([SentryEventScrubber::class, 'scrub'])
        ->and(config('sentry.max_request_body_size'))->toBe('never')
        ->and(config('sentry.send_default_pii'))->toBeFalse()
        ->and(config('sentry.ignore_transactions'))->toContain('/health');
});
