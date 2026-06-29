<?php

namespace App\Actions\Gateway;

class ProtocolTransformer
{
    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function toCanonical(string $incomingProtocol, array $payload): array
    {
        return match ($incomingProtocol) {
            'openai' => [
                'model' => (string) ($payload['model'] ?? ''),
                'messages' => $payload['messages'] ?? [],
                'tools' => $payload['tools'] ?? [],
                'stream' => (bool) ($payload['stream'] ?? false),
                'max_tokens' => $payload['max_tokens'] ?? $payload['max_completion_tokens'] ?? null,
                'temperature' => $payload['temperature'] ?? null,
                'system' => null,
            ],
            'anthropic' => [
                'model' => (string) ($payload['model'] ?? ''),
                'messages' => $payload['messages'] ?? [],
                'tools' => $payload['tools'] ?? [],
                'stream' => (bool) ($payload['stream'] ?? false),
                'max_tokens' => $payload['max_tokens'] ?? null,
                'temperature' => $payload['temperature'] ?? null,
                'system' => $payload['system'] ?? null,
            ],
            default => [],
        };
    }

    /**
     * @param  array<string, mixed>  $canonical
     * @return array<string, mixed>
     */
    public function toProviderPayload(array $canonical, string $providerProtocol): array
    {
        if ($providerProtocol === 'anthropic') {
            $systemMessages = collect($canonical['messages'] ?? [])
                ->filter(fn ($message) => ($message['role'] ?? null) === 'system')
                ->map(fn ($message) => $this->stringContent($message['content'] ?? ''))
                ->filter()
                ->values();

            $messages = collect($canonical['messages'] ?? [])
                ->reject(fn ($message) => ($message['role'] ?? null) === 'system')
                ->values()
                ->all();

            return $this->removeNulls([
                'model' => $canonical['model'] ?? null,
                'messages' => $messages,
                'tools' => $this->toAnthropicTools($canonical['tools'] ?? []),
                'stream' => (bool) ($canonical['stream'] ?? false),
                'max_tokens' => $canonical['max_tokens'] ?? null,
                'temperature' => $canonical['temperature'] ?? null,
                'system' => $canonical['system'] ?? ($systemMessages->isNotEmpty() ? $systemMessages->implode("\n") : null),
            ]);
        }

        $messages = collect($canonical['messages'] ?? [])->values()->all();

        if (! empty($canonical['system']) && ! collect($messages)->contains(fn ($message) => ($message['role'] ?? null) === 'system')) {
            array_unshift($messages, [
                'role' => 'system',
                'content' => $this->stringContent($canonical['system']),
            ]);
        }

        return $this->removeNulls([
            'model' => $canonical['model'] ?? null,
            'messages' => $messages,
            'tools' => $this->toOpenAiTools($canonical['tools'] ?? []),
            'stream' => (bool) ($canonical['stream'] ?? false),
            'max_tokens' => $canonical['max_tokens'] ?? null,
            'temperature' => $canonical['temperature'] ?? null,
        ]);
    }

    public function providerProtocol(string $adapterType): string
    {
        return match ($adapterType) {
            'anthropic_compatible' => 'anthropic',
            default => 'openai',
        };
    }

    /**
     * @param  array<string, mixed>  $response
     * @return array<string, mixed>
     */
    public function adaptResponse(array $response, string $incomingProtocol, string $providerProtocol, string $model): array
    {
        if ($incomingProtocol === $providerProtocol) {
            return $response;
        }

        return match ($incomingProtocol) {
            'openai' => $this->anthropicToOpenAi($response, $model),
            'anthropic' => $this->openAiToAnthropic($response, $model),
            default => $response,
        };
    }

    /**
     * @param  array<string, mixed>  $response
     * @return array{input: int, output: int, total: int}
     */
    public function extractUsage(array $response, string $incomingProtocol): array
    {
        if ($incomingProtocol === 'anthropic') {
            $input = (int) data_get($response, 'usage.input_tokens', 0);
            $output = (int) data_get($response, 'usage.output_tokens', 0);

            return [
                'input' => $input,
                'output' => $output,
                'total' => $input + $output,
            ];
        }

        $input = (int) data_get($response, 'usage.prompt_tokens', 0);
        $output = (int) data_get($response, 'usage.completion_tokens', 0);
        $total = (int) data_get($response, 'usage.total_tokens', $input + $output);

        return [
            'input' => $input,
            'output' => $output,
            'total' => $total,
        ];
    }

    /**
     * @param  array<string, mixed>  $response
     */
    public function extractToolCallsCount(array $response, string $incomingProtocol): int
    {
        if ($incomingProtocol === 'anthropic') {
            return collect(data_get($response, 'content', []))
                ->filter(fn ($block) => ($block['type'] ?? null) === 'tool_use')
                ->count();
        }

        return collect(data_get($response, 'choices.0.message.tool_calls', []))->count();
    }

    /**
     * @param  array<string, mixed>  $canonical
     */
    public function estimateInputTokens(array $canonical): int
    {
        $messages = collect($canonical['messages'] ?? []);
        $characters = $messages
            ->map(fn ($message) => $this->stringContent($message['content'] ?? ''))
            ->implode(' ');

        $chars = mb_strlen($characters);

        return max(1, (int) ceil($chars / 4));
    }

    /**
     * @return array<string, mixed>
     */
    public function errorPayload(string $incomingProtocol, string $message, string $code, string $type = 'invalid_request_error'): array
    {
        if ($incomingProtocol === 'anthropic') {
            return [
                'type' => 'error',
                'error' => [
                    'type' => $type,
                    'message' => $message,
                    'code' => $code,
                ],
            ];
        }

        return [
            'error' => [
                'type' => $type,
                'message' => $message,
                'code' => $code,
            ],
        ];
    }

    public function adaptStreamingFrame(string $frame, string $incomingProtocol, string $providerProtocol, string $model): string
    {
        if ($incomingProtocol === $providerProtocol) {
            return $frame;
        }

        $data = $this->extractSseData($frame);

        if ($data === null || $data === '') {
            return '';
        }

        if (trim($data) === '[DONE]') {
            if ($incomingProtocol === 'openai') {
                return "data: [DONE]\n\n";
            }

            return "event: message_stop\ndata: {\"type\":\"message_stop\"}\n\n";
        }

        $json = json_decode($data, true);

        if (! is_array($json)) {
            return '';
        }

        if ($incomingProtocol === 'openai' && $providerProtocol === 'anthropic') {
            return $this->anthropicFrameToOpenAi($json, $model);
        }

        if ($incomingProtocol === 'anthropic' && $providerProtocol === 'openai') {
            return $this->openAiFrameToAnthropic($json, $model);
        }

        return '';
    }

    /**
     * @param  array<int, array<string, mixed>>  $tools
     * @return array<int, array<string, mixed>>
     */
    protected function toAnthropicTools(array $tools): array
    {
        return collect($tools)
            ->map(function ($tool) {
                if (isset($tool['input_schema'])) {
                    return $tool;
                }

                return [
                    'name' => data_get($tool, 'function.name', data_get($tool, 'name')),
                    'description' => data_get($tool, 'function.description', data_get($tool, 'description')),
                    'input_schema' => data_get($tool, 'function.parameters', data_get($tool, 'input_schema', ['type' => 'object', 'properties' => []])),
                ];
            })
            ->filter(fn ($tool) => ! empty($tool['name']))
            ->values()
            ->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $tools
     * @return array<int, array<string, mixed>>
     */
    protected function toOpenAiTools(array $tools): array
    {
        return collect($tools)
            ->map(function ($tool) {
                if (($tool['type'] ?? null) === 'function') {
                    return $tool;
                }

                return [
                    'type' => 'function',
                    'function' => [
                        'name' => data_get($tool, 'name', data_get($tool, 'function.name')),
                        'description' => data_get($tool, 'description', data_get($tool, 'function.description')),
                        'parameters' => data_get($tool, 'input_schema', data_get($tool, 'function.parameters', ['type' => 'object', 'properties' => []])),
                    ],
                ];
            })
            ->filter(fn ($tool) => ! empty(data_get($tool, 'function.name')))
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $anthropicResponse
     * @return array<string, mixed>
     */
    protected function anthropicToOpenAi(array $anthropicResponse, string $model): array
    {
        $contentBlocks = collect(data_get($anthropicResponse, 'content', []));

        $text = $contentBlocks
            ->filter(fn ($block) => ($block['type'] ?? null) === 'text')
            ->map(fn ($block) => (string) ($block['text'] ?? ''))
            ->implode("\n");

        $toolCalls = $contentBlocks
            ->filter(fn ($block) => ($block['type'] ?? null) === 'tool_use')
            ->values()
            ->map(fn ($block) => [
                'id' => $block['id'] ?? null,
                'type' => 'function',
                'function' => [
                    'name' => (string) ($block['name'] ?? ''),
                    'arguments' => json_encode($block['input'] ?? [], JSON_UNESCAPED_UNICODE),
                ],
            ])
            ->all();

        $usage = $this->extractUsage($anthropicResponse, 'anthropic');

        return [
            'id' => data_get($anthropicResponse, 'id', 'chatcmpl_'.uniqid()),
            'object' => 'chat.completion',
            'created' => now()->timestamp,
            'model' => $model,
            'choices' => [
                [
                    'index' => 0,
                    'finish_reason' => data_get($anthropicResponse, 'stop_reason', 'stop'),
                    'message' => [
                        'role' => 'assistant',
                        'content' => $text,
                        'tool_calls' => $toolCalls,
                    ],
                ],
            ],
            'usage' => [
                'prompt_tokens' => $usage['input'],
                'completion_tokens' => $usage['output'],
                'total_tokens' => $usage['total'],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $openAiResponse
     * @return array<string, mixed>
     */
    protected function openAiToAnthropic(array $openAiResponse, string $model): array
    {
        $message = data_get($openAiResponse, 'choices.0.message', []);
        $contentText = $this->stringContent(data_get($message, 'content', ''));

        $content = [];

        if ($contentText !== '') {
            $content[] = ['type' => 'text', 'text' => $contentText];
        }

        foreach (data_get($message, 'tool_calls', []) as $toolCall) {
            $content[] = [
                'type' => 'tool_use',
                'id' => $toolCall['id'] ?? null,
                'name' => data_get($toolCall, 'function.name', ''),
                'input' => json_decode((string) data_get($toolCall, 'function.arguments', '{}'), true) ?: [],
            ];
        }

        $usage = $this->extractUsage($openAiResponse, 'openai');

        return [
            'id' => data_get($openAiResponse, 'id', 'msg_'.uniqid()),
            'type' => 'message',
            'role' => 'assistant',
            'model' => $model,
            'content' => $content,
            'stop_reason' => data_get($openAiResponse, 'choices.0.finish_reason', 'end_turn'),
            'usage' => [
                'input_tokens' => $usage['input'],
                'output_tokens' => $usage['output'],
            ],
        ];
    }

    protected function stringContent(mixed $content): string
    {
        if (is_string($content)) {
            return $content;
        }

        if (is_array($content)) {
            return collect($content)
                ->map(function ($part) {
                    if (is_string($part)) {
                        return $part;
                    }

                    if (is_array($part) && isset($part['text'])) {
                        return (string) $part['text'];
                    }

                    return '';
                })
                ->implode(' ');
        }

        return '';
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function removeNulls(array $payload): array
    {
        return collect($payload)
            ->reject(fn ($value) => $value === null)
            ->all();
    }

    protected function extractSseData(string $frame): ?string
    {
        $lines = preg_split('/\r\n|\r|\n/', trim($frame));
        $dataLines = collect($lines)
            ->filter(fn ($line) => str_starts_with((string) $line, 'data:'))
            ->map(fn ($line) => trim(substr((string) $line, 5)))
            ->values();

        if ($dataLines->isEmpty()) {
            return null;
        }

        return $dataLines->implode("\n");
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function anthropicFrameToOpenAi(array $payload, string $model): string
    {
        $type = (string) ($payload['type'] ?? '');

        if ($type === 'content_block_delta' && data_get($payload, 'delta.type') === 'text_delta') {
            $text = (string) data_get($payload, 'delta.text', '');

            if ($text === '') {
                return '';
            }

            $chunk = [
                'id' => 'chatcmpl_stream_'.uniqid(),
                'object' => 'chat.completion.chunk',
                'created' => now()->timestamp,
                'model' => $model,
                'choices' => [
                    [
                        'index' => 0,
                        'delta' => [
                            'content' => $text,
                        ],
                        'finish_reason' => null,
                    ],
                ],
            ];

            return 'data: '.json_encode($chunk, JSON_UNESCAPED_UNICODE)."\n\n";
        }

        if ($type === 'message_stop') {
            return "data: [DONE]\n\n";
        }

        return '';
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function openAiFrameToAnthropic(array $payload, string $model): string
    {
        $delta = data_get($payload, 'choices.0.delta', []);
        $text = (string) data_get($delta, 'content', '');
        $finishReason = data_get($payload, 'choices.0.finish_reason');

        if ($text !== '') {
            $event = [
                'type' => 'content_block_delta',
                'index' => 0,
                'delta' => [
                    'type' => 'text_delta',
                    'text' => $text,
                ],
            ];

            return "event: content_block_delta\ndata: ".json_encode($event, JSON_UNESCAPED_UNICODE)."\n\n";
        }

        if ($finishReason !== null) {
            $deltaEvent = [
                'type' => 'message_delta',
                'delta' => [
                    'stop_reason' => (string) $finishReason,
                ],
                'usage' => [
                    'output_tokens' => 0,
                    'input_tokens' => 0,
                ],
                'model' => $model,
            ];

            return "event: message_delta\ndata: ".json_encode($deltaEvent, JSON_UNESCAPED_UNICODE)."\n\n".
                "event: message_stop\ndata: {\"type\":\"message_stop\"}\n\n";
        }

        return '';
    }
}
