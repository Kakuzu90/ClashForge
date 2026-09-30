<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * NFR-OBS-1: every log line carries a request id (and, once authenticated, the user id), and each
 * request ends with one summary line. Context propagates into queued jobs dispatched here.
 */
class AssignRequestId
{
    private const INBOUND_PATTERN = '/^[A-Za-z0-9._-]{8,64}$/D';

    public function handle(Request $request, Closure $next): Response
    {
        $header = (string) config('platform.logging.request_id_header');
        $inbound = (string) $request->headers->get($header, '');
        $id = preg_match(self::INBOUND_PATTERN, $inbound) ? $inbound : Str::lower((string) Str::ulid());

        $request->attributes->set('request_id', $id);
        $request->attributes->set('request_started_at', microtime(true));
        Context::add('request_id', $id);

        $response = $next($request);
        $response->headers->set($header, $id);

        return $response;
    }

    public function terminate(Request $request, Response $response): void
    {
        $route = $request->route();
        $name = is_object($route) ? ($route->getName() ?? $route->uri()) : null;

        if (in_array($name, (array) config('platform.logging.skip_routes'), true)) {
            return;
        }

        $started = $request->attributes->get('request_started_at');

        Log::info('request', [
            'method' => $request->method(),
            'route' => $name,
            'status' => $response->getStatusCode(),
            'duration_ms' => is_float($started) ? (int) round((microtime(true) - $started) * 1000) : null,
        ]);
    }
}
