<?php

use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

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
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );

        // Preserve the OpenAI-style error envelope for unauthenticated API
        // requests; anything outside /api/* keeps Laravel's default handling
        // (e.g. Filament's login redirect).
        $exceptions->respond(function (Response $response, Throwable $e): Response {
            if (! $e instanceof AuthenticationException || $response->getStatusCode() !== 401) {
                return $response;
            }

            if (! request()->is('api/*')) {
                return $response;
            }

            return response()->json([
                'error' => [
                    'type' => 'authentication_error',
                    'message' => 'Unauthorized',
                ],
            ], 401);
        });
    })->create();
