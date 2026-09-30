<?php

use App\Domain\Media\Exceptions\StorageUnavailable;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            HandleInertiaRequests::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
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
