<?php

use App\Domain\Media\Exceptions\StorageUnavailable;
use App\Http\Controllers\Web\HealthController;
use App\Http\Middleware\AssignRequestId;
use App\Http\Middleware\EnforceAccountStatus;
use App\Http\Middleware\EnsureAccountIsActive;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\ThrottleHealth;
use App\Models\User;
use App\Support\Observability\PermissionDenialLog;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Sentry\Laravel\Integration;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: [__DIR__.'/../routes/web.php', __DIR__.'/../routes/admin.php'],
        commands: __DIR__.'/../routes/console.php',
        // Outside the web group: uptime pings must not start sessions or set cookies.
        then: function (): void {
            Route::get('/health', HealthController::class)->middleware(ThrottleHealth::class)->name('health');
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->prepend(AssignRequestId::class);

        $middleware->web(append: [
            HandleInertiaRequests::class,
            EnforceAccountStatus::class,
        ]);

        $middleware->alias([
            'account.active' => EnsureAccountIsActive::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        Integration::handles($exceptions);

        // Every policy or Gate denial reaches the `security` log (specs/11 §3). Rendering is unchanged.
        $exceptions->render(function (AccessDeniedHttpException $e, Request $request) {
            if ($e->getPrevious() instanceof AuthorizationException) {
                PermissionDenialLog::record($request, ($user = $request->user()) instanceof User ? $user->ulid : null);
            }

            return null;
        });

        // The upload endpoints are JSON only, whatever the Accept header says.
        $exceptions->shouldRenderJsonWhen(fn (Request $request): bool => $request->is('uploads/*') || $request->expectsJson());

        // Hide model class names that Laravel puts in 404 messages.
        $exceptions->render(fn (NotFoundHttpException $e, Request $request) => $request->is('uploads/*')
            ? response()->json(['message' => 'Not found.'], 404)
            : null);

        $exceptions->render(fn (StorageUnavailable $e) => response()->json([
            'message' => 'Uploads are temporarily unavailable. Try again in a few minutes.',
        ], 503));
    })->create();
