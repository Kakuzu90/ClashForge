<?php

namespace App\Support\Observability;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Context;
use Sentry\Event;
use Sentry\EventHint;
use Sentry\UserDataBag;

/**
 * Last stop before an error or a transaction leaves for Sentry. `send_default_pii` is already off;
 * this also drops anything that identifies a person or a session, keeping only the user id.
 */
final class SentryEventScrubber
{
    // Credentials, session tokens and the client IP as proxies and the CDN edge forward it.
    private const SENSITIVE_HEADERS = [
        'authorization', 'proxy-authorization', 'cookie', 'set-cookie', 'x-xsrf-token', 'x-csrf-token',
        'x-forwarded-for', 'x-real-ip', 'cf-connecting-ip', 'true-client-ip', 'forwarded', 'x-client-ip',
    ];

    public static function scrub(Event $event, ?EventHint $hint = null): Event
    {
        self::scrubRequest($event);
        self::scrubUser($event);
        self::scrubExceptions($event, $hint);

        $requestId = Context::get('request_id');

        if (is_string($requestId)) {
            $event->setTag('request_id', $requestId);
        }

        return $event;
    }

    private static function scrubRequest(Event $event): void
    {
        $request = $event->getRequest();

        if ($request === []) {
            return;
        }

        unset($request['cookies'], $request['data'], $request['env']);

        // Query strings carry reset tokens, signatures and emails: keep the path only.
        if (isset($request['url']) && is_string($request['url'])) {
            $request['url'] = strtok($request['url'], '?') ?: $request['url'];
        }

        if (isset($request['query_string'])) {
            $request['query_string'] = '[filtered]';
        }

        if (isset($request['headers']) && is_array($request['headers'])) {
            $request['headers'] = array_filter(
                $request['headers'],
                fn (mixed $_, string $name): bool => ! in_array(strtolower($name), self::SENSITIVE_HEADERS, true),
                ARRAY_FILTER_USE_BOTH,
            );
        }

        $event->setRequest($request);
    }

    private static function scrubUser(Event $event): void
    {
        $user = $event->getUser();

        if ($user !== null) {
            $event->setUser($user->getId() === null ? null : UserDataBag::createFromUserIdentifier($user->getId()));
        }
    }

    /**
     * Frame arguments are dropped (php.ini already ignores them; this covers other runtimes), and
     * query errors lose their interpolated bindings: SQLSTATE and the placeholder SQL remain.
     */
    private static function scrubExceptions(Event $event, ?EventHint $hint): void
    {
        foreach ($event->getExceptions() as $exception) {
            foreach ($exception->getStacktrace()?->getFrames() ?? [] as $frame) {
                $frame->setVars([]);
            }
        }

        $original = $hint?->exception;

        if ($original instanceof QueryException) {
            foreach ($event->getExceptions() as $exception) {
                if ($exception->getType() === $original::class) {
                    $exception->setValue(QueryExceptionRedactor::describe($original));
                }
            }
        }
    }
}
