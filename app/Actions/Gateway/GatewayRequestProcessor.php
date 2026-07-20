<?php

namespace App\Actions\Gateway;

use App\Models\LlmModel;
use App\Models\LlmProvider;
use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response as HttpResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

class GatewayRequestProcessor
{
    public function __construct(
        private readonly ResolveProviderSecret $resolveProviderSecret,
    ) {
        //
    }

    /**
     * Handle an OpenAI-compatible embeddings request.
     *
     * Unlike chat completions, embeddings are always non-streaming and only
     * consume input tokens. The response is returned as-is from the provider
     * (OpenAI-compatible) since there is no Anthropic embeddings protocol to
     * translate from.
     */
    public function handleEmbeddings(Request $request): Response
    {
        /** @var User|null $user */
        $user = $request->user();
        /** @var PersonalAccessToken|null $token */
        $token = $user?->currentAccessToken();

        if (! $user || ! $token) {
            return response()->json(
                $this->errorPayload(
                    'openai',
                    'Unauthorized',
                    'unauthorized',
                    'authentication_error',
                ),
                401,
            );
        }

        /** @var array<string, mixed> $payload */
        $payload = $request->json()->all();

        $requestedModel = (string) ($payload['model'] ?? '');

        if ($requestedModel === '') {
            return response()->json(
                $this->errorPayload(
                    'openai',
                    'The model field is required.',
                    'missing_model',
                ),
                422,
            );
        }

        $input = $payload['input'] ?? null;

        if ($input === null || $input === '') {
            return response()->json(
                $this->errorPayload(
                    'openai',
                    'The input field is required.',
                    'missing_input',
                ),
                422,
            );
        }

        $model = $this->resolveModelForUser($requestedModel);

        if (! $model) {
            return response()->json(
                $this->errorPayload(
                    'openai',
                    'Model is unavailable for this user.',
                    'model_unavailable',
                ),
                422,
            );
        }

        $provider = $model->provider;

        $headers = $this->providerHeaders($provider, 'openai');
        $endpoint = (string) data_get(
            $provider->options,
            'endpoints.embeddings',
            '/v1/embeddings',
        );
        $url = rtrim($provider->base_url, '/').$endpoint;

        $providerPayload = collect([
            'model' => $model->external_model_id,
            'input' => $input,
            'encoding_format' => $payload['encoding_format'] ?? null,
            'dimensions' => $payload['dimensions'] ?? null,
        ])
            ->reject(fn ($value) => $value === null)
            ->all();

        try {
            $response = $this->sendWithRetry(
                $headers,
                $url,
                $providerPayload,
                false,
            );
        } catch (ConnectionException) {
            return response()->json(
                $this->errorPayload(
                    'openai',
                    'Provider timeout.',
                    'provider_timeout',
                    'api_error',
                ),
                504,
            );
        }

        $status = $response->status();
        $body = $response->json();

        if (! is_array($body)) {
            $body = ['error' => ['message' => (string) $response->body()]];
        }

        return response()->json($body, $status);
    }

    public function handle(
        Request $request,
        string $incomingProtocol,
        string $incomingEndpoint,
    ): Response {
        /** @var User|null $user */
        $user = $request->user();
        /** @var PersonalAccessToken|null $token */
        $token = $user?->currentAccessToken();

        if (! $user || ! $token) {
            return response()->json(
                $this->errorPayload(
                    $incomingProtocol,
                    'Unauthorized',
                    'unauthorized',
                    'authentication_error',
                ),
                401,
            );
        }

        /** @var array<string, mixed> $payload */
        $payload = $request->json()->all();

        $requestedModel = (string) ($payload['model'] ?? '');

        if ($requestedModel === '') {
            return response()->json(
                $this->errorPayload(
                    $incomingProtocol,
                    'The model field is required.',
                    'missing_model',
                ),
                422,
            );
        }

        $model = $this->resolveModelForUser($requestedModel);

        if (! $model) {
            return response()->json(
                $this->errorPayload(
                    $incomingProtocol,
                    'Model is unavailable for this user.',
                    'model_unavailable',
                ),
                422,
            );
        }

        $provider = $model->provider;
        $providerProtocol = $this->providerProtocol(
            $provider->adapter_type,
        );

        // Transparent 1:1 passthrough: the client must speak the same protocol
        // as the upstream provider. Cross-protocol translation has been removed,
        // so a mismatch is rejected rather than silently converted.
        if ($incomingProtocol !== $providerProtocol) {
            return response()->json(
                $this->errorPayload(
                    $incomingProtocol,
                    'The requested model is not available in the '.
                        $incomingProtocol.' API format.',
                    'protocol_mismatch',
                ),
                422,
            );
        }

        // Forward the request body as-is, only swapping the model identifier for
        // the provider's external id (a no-op when they already match).
        $providerPayload = $payload;
        $providerPayload['model'] = $model->external_model_id;

        $isStreaming = (bool) ($payload['stream'] ?? false);

        $headers = $this->providerHeaders($provider, $providerProtocol);
        $endpoint = $this->providerEndpoint(
            $provider,
            $providerProtocol,
            $incomingEndpoint,
        );
        $url = rtrim($provider->base_url, '/').$endpoint;

        if ($isStreaming) {
            return $this->streamToClient(
                protocol: $incomingProtocol,
                url: $url,
                headers: $headers,
                providerPayload: $providerPayload,
            );
        }

        return $this->forwardAsJson(
            incomingProtocol: $incomingProtocol,
            url: $url,
            headers: $headers,
            providerPayload: $providerPayload,
        );
    }

    /**
     * Resolve the LLM model for a given external model id.
     *
     * Any active model on an active provider is reachable by any authenticated
     * caller — entitlement/subscription gating was removed in favor of a
     * transparent passthrough proxy.
     */
    protected function resolveModelForUser(string $externalModelId): ?LlmModel
    {
        $model = LlmModel::query()
            ->with('provider')
            ->where('external_model_id', $externalModelId)
            ->where('is_active', true)
            ->whereHas('provider', fn ($query) => $query->where('is_active', true))
            ->first();

        if (! $model || ! $model->provider instanceof LlmProvider) {
            return null;
        }

        return $model;
    }

    /**
     * @param  array<string, string>  $headers
     * @param  array<string, mixed>  $providerPayload
     */
    protected function forwardAsJson(
        string $incomingProtocol,
        string $url,
        array $headers,
        array $providerPayload,
    ): Response {
        try {
            $response = $this->sendWithRetry(
                $headers,
                $url,
                $providerPayload,
                false,
            );
        } catch (ConnectionException) {
            return response()->json(
                $this->errorPayload(
                    $incomingProtocol,
                    'Provider timeout.',
                    'provider_timeout',
                    'api_error',
                ),
                504,
            );
        }

        $status = $response->status();
        $body = $response->json();

        if (! is_array($body)) {
            $body = [
                'error' => [
                    'message' => (string) $response->body(),
                ],
            ];
        }

        return response()->json($body, $status);
    }

    /**
     * @param  array<string, string>  $headers
     * @param  array<string, mixed>  $providerPayload
     */
    protected function streamToClient(
        string $protocol,
        string $url,
        array $headers,
        array $providerPayload,
    ): Response {
        try {
            $response = $this->sendWithRetry(
                $headers,
                $url,
                $providerPayload,
                true,
            );
        } catch (ConnectionException) {
            return response()->json(
                $this->errorPayload(
                    $protocol,
                    'Provider timeout.',
                    'provider_timeout',
                    'api_error',
                ),
                504,
            );
        }

        $status = $response->status();
        $psrBody = $response->toPsrResponse()->getBody();

        return response()->stream(
            function () use ($psrBody): void {
                while (! $psrBody->eof()) {
                    echo $psrBody->read(8192);
                    flush();
                }
            },
            $status,
            [
                'Content-Type' => $response->header('Content-Type') ?: 'text/event-stream',
                'Cache-Control' => 'no-cache',
                'Connection' => 'keep-alive',
                'X-Accel-Buffering' => 'no',
            ],
        );
    }

    /**
     * @return array<string, string>
     */
    protected function providerHeaders(
        LlmProvider $provider,
        string $providerProtocol,
    ): array {
        $resolvedSecret = $this->resolveProviderSecret->handle(
            $provider->secret_ref,
        );

        $headers = [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ];

        if ($provider->auth_mode === 'bearer' && $resolvedSecret) {
            $headers['Authorization'] = 'Bearer '.$resolvedSecret;
        }

        if ($provider->auth_mode === 'header' && $resolvedSecret) {
            $headerName = (string) data_get(
                $provider->options,
                'auth_header',
                'x-api-key',
            );
            $headers[$headerName] = $resolvedSecret;
        }

        if ($providerProtocol === 'anthropic') {
            $headers['anthropic-version'] = (string) config(
                'services.llm_gateway.anthropic_version',
                '2023-06-01',
            );
        }

        foreach (
            (array) data_get($provider->options, 'headers', []) as $name => $value
        ) {
            if (is_string($name) && is_string($value)) {
                $headers[$name] = $value;
            }
        }

        return $headers;
    }

    protected function providerEndpoint(
        LlmProvider $provider,
        string $providerProtocol,
        string $incomingEndpoint,
    ): string {
        if ($providerProtocol === 'anthropic') {
            return (string) data_get(
                $provider->options,
                'endpoints.messages',
                '/v1/messages',
            );
        }

        if ($incomingEndpoint === '/v1/responses') {
            return (string) data_get(
                $provider->options,
                'endpoints.responses',
                '/v1/responses',
            );
        }

        return (string) data_get(
            $provider->options,
            'endpoints.chat',
            '/v1/chat/completions',
        );
    }

    /**
     * @param  array<string, string>  $headers
     * @param  array<string, mixed>  $payload
     */
    protected function sendWithRetry(
        array $headers,
        string $url,
        array $payload,
        bool $stream,
    ): HttpResponse {
        $attempts = max(
            1,
            (int) config('services.llm_gateway.retry_attempts', 2),
        );
        $backoffMs = max(
            0,
            (int) config('services.llm_gateway.retry_backoff_ms', 150),
        );

        $request = Http::withHeaders($headers)
            ->timeout((int) config('services.llm_gateway.timeout_seconds', 120))
            ->retry(
                $attempts,
                $backoffMs,
                fn ($exception) => $exception instanceof ConnectionException
                    || ($exception instanceof RequestException && $exception->response->status() >= 500),
            );

        if ($stream) {
            $request = $request->withOptions(['stream' => true]);
        }

        return $request->send('POST', $url, ['json' => $payload]);
    }

    /**
     * Map a provider adapter type to the wire protocol it expects.
     */
    protected function providerProtocol(string $adapterType): string
    {
        return match ($adapterType) {
            'anthropic_compatible' => 'anthropic',
            default => 'openai',
        };
    }

    /**
     * Produce a protocol-aware error payload. The gateway is a transparent
     * 1:1 passthrough and does no request/response translation, so the only
     * protocol-specific behaviour is the shape of the error envelope
     * (Anthropic nests errors under a `type: error` wrapper).
     *
     * @return array<string, mixed>
     */
    protected function errorPayload(
        string $incomingProtocol,
        string $message,
        string $code,
        string $type = 'invalid_request_error',
    ): array {
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
}
