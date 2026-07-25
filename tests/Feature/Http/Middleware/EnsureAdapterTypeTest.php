<?php

use App\Http\Middleware\EnsureAdapterType;
use App\Models\AiProvider;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

it('allows the request when adapter_type is in the allowed list', function () {
    $provider = new AiProvider(['adapter_type' => 'openai_compatible']);
    $request = Request::create('/api/v1/responses', 'POST');
    $request->attributes->set('gateway.provider', $provider);

    $ran = false;
    $response = (new EnsureAdapterType)->handle(
        $request,
        function (Request $req) use (&$ran) {
            $ran = true;

            return response()->noContent();
        },
        'openai_compatible,openai',
        'openai',
    );

    expect($ran)->toBeTrue();
    expect($response->getStatusCode())->toBe(204);
});

it('rejects with the OpenAI envelope when adapter_type is not allowed', function () {
    $provider = new AiProvider(['adapter_type' => 'anthropic_compatible']);
    $request = Request::create('/api/v1/responses', 'POST');
    $request->attributes->set('gateway.provider', $provider);

    $ran = false;
    $response = (new EnsureAdapterType)->handle(
        $request,
        function () use (&$ran) {
            $ran = true;

            return response()->noContent();
        },
        'openai_compatible,openai',
        'openai',
    );

    expect($ran)->toBeFalse();
    expect($response)->toBeInstanceOf(JsonResponse::class);
    expect($response->getStatusCode())->toBe(422);
    expect(json_decode($response->getContent(), true))->toBe([
        'error' => [
            'type' => 'invalid_request_error',
            'message' => 'The requested model is not available in the openai API format.',
            'code' => 'protocol_mismatch',
        ],
    ]);
});

it('rejects with the Anthropic envelope when errorFormat is anthropic', function () {
    $provider = new AiProvider(['adapter_type' => 'openai_compatible']);
    $request = Request::create('/api/v1/messages', 'POST');
    $request->attributes->set('gateway.provider', $provider);

    $ran = false;
    $response = (new EnsureAdapterType)->handle(
        $request,
        function () use (&$ran) {
            $ran = true;

            return response()->noContent();
        },
        'anthropic_compatible',
        'anthropic',
    );

    expect($ran)->toBeFalse();
    expect($response)->toBeInstanceOf(JsonResponse::class);
    expect($response->getStatusCode())->toBe(422);
    expect(json_decode($response->getContent(), true))->toBe([
        'type' => 'error',
        'error' => [
            'type' => 'invalid_request_error',
            'message' => 'The requested model is not available in the anthropic API format.',
            'code' => 'protocol_mismatch',
        ],
    ]);
});

it('supports multiple allowed adapter types in a comma-separated list', function () {
    $provider = new AiProvider(['adapter_type' => 'openai']);
    $request = Request::create('/api/v1/responses', 'POST');
    $request->attributes->set('gateway.provider', $provider);

    $ran = false;
    $response = (new EnsureAdapterType)->handle(
        $request,
        function () use (&$ran) {
            $ran = true;

            return response()->noContent();
        },
        'openai_compatible,openai',
        'openai',
    );

    expect($ran)->toBeTrue();
    expect($response->getStatusCode())->toBe(204);
});
