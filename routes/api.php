<?php

use App\Http\Controllers\Billing\StripeWebhookController;
use App\Http\Controllers\Gateway\CompatibilityGatewayController;
use App\Http\Middleware\AuthenticateApiKey;
use App\Http\Middleware\ThrottleApiKeyRequests;
use Illuminate\Support\Facades\Route;

Route::post('webhooks/stripe', StripeWebhookController::class);

Route::prefix('v1')
    ->middleware([AuthenticateApiKey::class, ThrottleApiKeyRequests::class])
    ->group(function () {
        Route::post('chat/completions', [CompatibilityGatewayController::class, 'openAiChatCompletions']);
        Route::post('responses', [CompatibilityGatewayController::class, 'openAiResponses']);
        Route::post('messages', [CompatibilityGatewayController::class, 'anthropicMessages']);
    });
