<?php

use App\Http\Controllers\Api\V1\ModelsController;
use App\Http\Middleware\EnsureAdapterType;
use App\Http\Middleware\ResolveGatewayProvider;
use App\Models\AiProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')
    ->middleware(['auth:sanctum', 'throttle:api'])
    ->group(function () {
        Route::get('models', ModelsController::class);

        Route::gateway('chat/completions', function ($route) {
            $route->to(function (Request $request) {
                /** @var AiProvider $aiProvider */
                $aiProvider = $request->attributes->get('gateway.provider');

                return rtrim($aiProvider->base_url, '/')
                    .(string) data_get($aiProvider->options, 'endpoints.chat', '/v1/chat/completions');
            });
        })->middleware(ResolveGatewayProvider::class);

        Route::gateway('embeddings', function ($route) {
            $route
                ->to(function (Request $request) {
                    /** @var AiProvider $aiProvider */
                    $aiProvider = $request->attributes->get('gateway.provider');

                    return rtrim($aiProvider->base_url, '/')
                        .(string) data_get($aiProvider->options, 'endpoints.embeddings', '/v1/embeddings');
                })
                ->streaming(false)
                ->timeout((int) config('services.llm_gateway.timeout_seconds', 120));
        })->middleware(ResolveGatewayProvider::class);

        Route::gateway('responses', function ($route) {
            $route
                ->to(function (Request $request) {
                    /** @var AiProvider $aiProvider */
                    $aiProvider = $request->attributes->get('gateway.provider');

                    return rtrim($aiProvider->base_url, '/')
                        .(string) data_get($aiProvider->options, 'endpoints.responses', '/v1/responses');
                })
                ->timeout((int) config('services.llm_gateway.timeout_seconds', 120));
        })->middleware([
            ResolveGatewayProvider::class,
            EnsureAdapterType::class.':openai_compatible,openai',
        ]);

        Route::gateway('messages', function ($route) {
            $route
                ->to(function (Request $request) {
                    /** @var AiProvider $aiProvider */
                    $aiProvider = $request->attributes->get('gateway.provider');

                    return rtrim($aiProvider->base_url, '/')
                        .(string) data_get($aiProvider->options, 'endpoints.messages', '/v1/messages');
                })
                ->timeout((int) config('services.llm_gateway.timeout_seconds', 120));
        })->middleware([
            ResolveGatewayProvider::class,
            EnsureAdapterType::class.':anthropic_compatible,anthropic',
        ]);
    });
