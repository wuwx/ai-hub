<?php

use App\Http\Controllers\Api\V1\ModelsController;
use App\Http\Gateway\Handlers\AiGatewayHandler;
use App\Http\Middleware\EnsureAdapterType;
use App\Http\Middleware\ResolveGatewayProvider;
use Illuminate\Support\Facades\Route;
use Wuwx\LaravelGateway\Facades\Gateway;

Route::prefix('v1')
    ->middleware(['auth:sanctum', 'throttle:api'])
    ->group(function () {
        Route::get('models', ModelsController::class);

        Gateway::any('chat/completions', AiGatewayHandler::class)
            ->middleware(ResolveGatewayProvider::class);

        Gateway::any('embeddings', AiGatewayHandler::class)
            ->middleware(ResolveGatewayProvider::class);

        Gateway::any('responses', AiGatewayHandler::class)
            ->middleware([
                ResolveGatewayProvider::class,
                EnsureAdapterType::class.':openai_compatible,openai',
            ]);

        Gateway::any('messages', AiGatewayHandler::class)
            ->middleware([
                ResolveGatewayProvider::class,
                EnsureAdapterType::class.':anthropic_compatible,anthropic',
            ]);
    });
