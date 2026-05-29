<?php

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (Throwable $e, Request $request) {
            if (! $request->expectsJson()) {
                return null;
            }

            return match (true) {
                $e instanceof ModelNotFoundException =>
                    response()->json([
                        'success' => false,
                        'message' => 'Note not found.',
                    ], 404),

                $e instanceof ValidationException =>
                    response()->json([
                        'success' => false,
                        'message' => 'Validation failed.',
                        'errors' => $e->errors(),
                    ], 422),

                $e instanceof AuthorizationException =>
                    response()->json([
                        'success' => false,
                        'message' => 'Unauthorized',
                    ], 403),

                default =>
                    response()->json([
                        'success' => false,
                        'message' => app()->isProduction() ? 'Server Error.' : $e->getMessage(),
                    ], 500),
            };
        });
    })->create();
