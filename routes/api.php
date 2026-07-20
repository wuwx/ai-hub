<?php

use App\Http\Controllers\Api\V1\Chat\CompletionsController;
use App\Http\Controllers\Api\V1\EmbeddingsController;
use App\Http\Controllers\Api\V1\MessagesController;
use App\Http\Controllers\Api\V1\ModelsController;
use App\Http\Controllers\Api\V1\ResponsesController;
use App\Http\Controllers\HealthCheckController;
use App\Http\Controllers\PrometheusMetricsController;
use Illuminate\Support\Facades\Route;

Route::get('health', HealthCheckController::class);

Route::get('metrics', PrometheusMetricsController::class);

Route::prefix('v1')
    ->middleware(['auth:sanctum'])
    ->group(function () {
        Route::get('models', [ModelsController::class, 'index']);
        Route::post('chat/completions', CompletionsController::class);
        Route::post('embeddings', EmbeddingsController::class);
        Route::post('responses', ResponsesController::class);
        Route::post('messages', MessagesController::class);

    });
