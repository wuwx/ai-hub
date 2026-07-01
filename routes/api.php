<?php

use App\Http\Controllers\Gateway\CompatibilityGatewayController;
use App\Http\Controllers\HealthCheckController;
use App\Http\Controllers\PrometheusMetricsController;
use App\Http\Middleware\AuthenticateApiKey;
use App\Http\Middleware\EnforceConcurrentRequestLimit;
use App\Http\Middleware\ThrottleApiKeyRequests;
use Illuminate\Support\Facades\Route;

Route::get('health', HealthCheckController::class);

Route::get('metrics', PrometheusMetricsController::class);

Route::prefix('v1')
    ->middleware([AuthenticateApiKey::class, ThrottleApiKeyRequests::class, EnforceConcurrentRequestLimit::class])
    ->group(function () {
        Route::get('models', [CompatibilityGatewayController::class, 'listModels']);
        Route::post('chat/completions', [CompatibilityGatewayController::class, 'openAiChatCompletions']);
        Route::post('embeddings', [CompatibilityGatewayController::class, 'openAiEmbeddings']);
        Route::post('responses', [CompatibilityGatewayController::class, 'openAiResponses']);
        Route::post('messages', [CompatibilityGatewayController::class, 'anthropicMessages']);
    });
