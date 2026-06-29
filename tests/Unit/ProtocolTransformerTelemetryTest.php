<?php

use App\Actions\Gateway\ProtocolTransformer;

it('estimates at least one token for empty text', function () {
    $transformer = new ProtocolTransformer;

    expect($transformer->estimateTextTokens(''))->toBe(1);
});

it('estimates more tokens for CJK text than equivalent-length ASCII', function () {
    $transformer = new ProtocolTransformer;

    $asciiTokens = $transformer->estimateTextTokens(str_repeat('a', 40));
    $cjkTokens = $transformer->estimateTextTokens(str_repeat('中', 40));

    expect($cjkTokens)->toBeGreaterThan($asciiTokens);
});

it('estimates roughly 1 token per 4 ASCII characters', function () {
    $transformer = new ProtocolTransformer;

    $tokens = $transformer->estimateTextTokens(str_repeat('a', 40));

    // 40 ASCII chars / 4 = 10 tokens
    expect($tokens)->toBe(10);
});

it('estimates roughly 1 token per 1.5 CJK characters', function () {
    $transformer = new ProtocolTransformer;

    $tokens = $transformer->estimateTextTokens(str_repeat('中', 15));

    // 15 CJK chars / 1.5 = 10 tokens
    expect($tokens)->toBe(10);
});

it('extracts input tokens from anthropic message_start frame', function () {
    $transformer = new ProtocolTransformer;

    $frame = "event: message_start\ndata: ".json_encode([
        'type' => 'message_start',
        'message' => [
            'usage' => [
                'input_tokens' => 42,
                'output_tokens' => 0,
            ],
        ],
    ]);

    $telemetry = $transformer->extractStreamTelemetry($frame, 'anthropic');

    expect($telemetry['input'])->toBe(42);
    expect($telemetry['output'])->toBe(0);
    expect($telemetry['text'])->toBe('');
});

it('extracts output tokens from anthropic message_delta frame', function () {
    $transformer = new ProtocolTransformer;

    $frame = "event: message_delta\ndata: ".json_encode([
        'type' => 'message_delta',
        'usage' => [
            'output_tokens' => 128,
        ],
    ]);

    $telemetry = $transformer->extractStreamTelemetry($frame, 'anthropic');

    expect($telemetry['input'])->toBe(0);
    expect($telemetry['output'])->toBe(128);
});

it('extracts text delta from anthropic content_block_delta frame', function () {
    $transformer = new ProtocolTransformer;

    $frame = "event: content_block_delta\ndata: ".json_encode([
        'type' => 'content_block_delta',
        'delta' => [
            'type' => 'text_delta',
            'text' => 'Hello world',
        ],
    ]);

    $telemetry = $transformer->extractStreamTelemetry($frame, 'anthropic');

    expect($telemetry['text'])->toBe('Hello world');
    expect($telemetry['input'])->toBe(0);
    expect($telemetry['output'])->toBe(0);
});

it('extracts usage from OpenAI stream chunk with usage field', function () {
    $transformer = new ProtocolTransformer;

    $frame = 'data: '.json_encode([
        'choices' => [
            ['delta' => ['content' => 'response']],
        ],
        'usage' => [
            'prompt_tokens' => 15,
            'completion_tokens' => 25,
        ],
    ]);

    $telemetry = $transformer->extractStreamTelemetry($frame, 'openai');

    expect($telemetry['input'])->toBe(15);
    expect($telemetry['output'])->toBe(25);
    expect($telemetry['text'])->toBe('response');
});

it('extracts text delta from OpenAI stream chunk without usage', function () {
    $transformer = new ProtocolTransformer;

    $frame = 'data: '.json_encode([
        'choices' => [
            ['delta' => ['content' => 'streaming text']],
        ],
    ]);

    $telemetry = $transformer->extractStreamTelemetry($frame, 'openai');

    expect($telemetry['text'])->toBe('streaming text');
    expect($telemetry['input'])->toBe(0);
    expect($telemetry['output'])->toBe(0);
});

it('returns zeros for the [DONE] sentinel frame', function () {
    $transformer = new ProtocolTransformer;

    $telemetry = $transformer->extractStreamTelemetry('data: [DONE]', 'openai');

    expect($telemetry)->toBe(['input' => 0, 'output' => 0, 'text' => '']);
});

it('returns zeros for an empty or malformed frame', function () {
    $transformer = new ProtocolTransformer;

    expect($transformer->extractStreamTelemetry('', 'openai'))->toBe(['input' => 0, 'output' => 0, 'text' => '']);
    expect($transformer->extractStreamTelemetry('data: not-json', 'openai'))->toBe(['input' => 0, 'output' => 0, 'text' => '']);
});
