<?php

namespace App\Actions\Gateway;

use App\Actions\Usage\EnforceTeamTokenQuota;
use App\Actions\Usage\RecordApiRequestUsage;
use App\Exceptions\QuotaExceededException;
use App\Models\ApiKey;
use App\Models\LlmModel;
use App\Models\LlmProvider;
use App\Models\Team;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response as HttpResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class GatewayRequestProcessor
{
    public function __construct(
        private readonly ProtocolTransformer $protocolTransformer,
        private readonly ResolveProviderSecret $resolveProviderSecret,
        private readonly EnforceTeamTokenQuota $enforceTeamTokenQuota,
        private readonly RecordApiRequestUsage $recordApiRequestUsage,
    ) {
        //
    }

    public function handle(Request $request, string $incomingProtocol, string $incomingEndpoint): Response
    {
        /** @var Team|null $team */
        $team = $request->attributes->get('gateway.team');
        /** @var ApiKey|null $apiKey */
        $apiKey = $request->attributes->get('gateway.api_key');

        $traceId = (string) ($request->attributes->get('gateway.trace_id') ?: Str::uuid()->toString());
        $request->attributes->set('gateway.trace_id', $traceId);

        if (! $team || ! $apiKey) {
            return $this->jsonWithTrace(
                $this->protocolTransformer->errorPayload($incomingProtocol, 'Unauthorized', 'unauthorized', 'authentication_error'),
                401,
                $traceId,
            );
        }

        /** @var array<string, mixed> $payload */
        $payload = $request->json()->all();

        $requestedModel = (string) ($payload['model'] ?? '');

        if ($requestedModel === '') {
            return $this->jsonWithTrace(
                $this->protocolTransformer->errorPayload($incomingProtocol, 'The model field is required.', 'missing_model'),
                422,
                $traceId,
            );
        }

        $model = $this->resolveModelForTeam($team, $requestedModel);

        if (! $model) {
            return $this->jsonWithTrace(
                $this->protocolTransformer->errorPayload($incomingProtocol, 'Model is unavailable for this team.', 'model_unavailable'),
                422,
                $traceId,
            );
        }

        $provider = $model->provider;
        $providerProtocol = $this->protocolTransformer->providerProtocol($provider->adapter_type);

        $canonical = $this->protocolTransformer->toCanonical($incomingProtocol, $payload);
        $canonical['model'] = $model->external_model_id;

        $isStreaming = (bool) ($canonical['stream'] ?? false);

        $providerPayload = $this->protocolTransformer->toProviderPayload($canonical, $providerProtocol);
        $tokenInputEstimate = $this->protocolTransformer->estimateInputTokens($canonical);

        $idempotencyKey = (string) $request->header('Idempotency-Key', '');
        $payloadHash = hash('sha256', json_encode($payload, JSON_UNESCAPED_UNICODE));

        $idempotencyCacheKey = null;

        if (! $isStreaming && $idempotencyKey !== '') {
            $idempotencyCacheKey = $this->idempotencyCacheKey($team, $apiKey, $incomingEndpoint, $idempotencyKey);
            $cachedResponse = Cache::get($idempotencyCacheKey);

            if (is_array($cachedResponse)) {
                if (($cachedResponse['payload_hash'] ?? null) !== $payloadHash) {
                    return $this->jsonWithTrace(
                        $this->protocolTransformer->errorPayload(
                            $incomingProtocol,
                            'Idempotency key reused with different payload.',
                            'idempotency_payload_mismatch',
                        ),
                        409,
                        $traceId,
                    );
                }

                return $this->jsonWithTrace(
                    $cachedResponse['body'] ?? [],
                    (int) ($cachedResponse['status'] ?? 200),
                    $traceId,
                    ['X-Idempotent-Replay' => 'true'],
                );
            }
        }

        try {
            $this->enforceTeamTokenQuota->handle($team, $tokenInputEstimate);
        } catch (QuotaExceededException $exception) {
            return $this->jsonWithTrace(
                $this->protocolTransformer->errorPayload($incomingProtocol, $exception->getMessage(), 'quota_exceeded', 'rate_limit_error'),
                429,
                $traceId,
            );
        }

        if ($this->isProviderCircuitOpen($provider)) {
            return $this->jsonWithTrace(
                $this->protocolTransformer->errorPayload($incomingProtocol, 'Provider is temporarily unavailable. Please retry later.', 'provider_circuit_open', 'api_error'),
                503,
                $traceId,
            );
        }

        $headers = $this->providerHeaders($provider, $providerProtocol);
        $endpoint = $this->providerEndpoint($provider, $providerProtocol, $incomingEndpoint);
        $url = rtrim($provider->base_url, '/').$endpoint;

        if ($isStreaming) {
            return $this->streamToClient(
                team: $team,
                apiKey: $apiKey,
                provider: $provider,
                model: $model,
                protocol: $incomingProtocol,
                endpoint: $incomingEndpoint,
                url: $url,
                headers: $headers,
                providerPayload: $providerPayload,
                traceId: $traceId,
                inputEstimate: $tokenInputEstimate,
                providerProtocol: $providerProtocol,
            );
        }

        return $this->forwardAsJson(
            team: $team,
            apiKey: $apiKey,
            provider: $provider,
            model: $model,
            incomingProtocol: $incomingProtocol,
            providerProtocol: $providerProtocol,
            endpoint: $incomingEndpoint,
            url: $url,
            headers: $headers,
            providerPayload: $providerPayload,
            traceId: $traceId,
            inputEstimate: $tokenInputEstimate,
            idempotencyCacheKey: $idempotencyCacheKey,
            payloadHash: $payloadHash,
        );
    }

    protected function resolveModelForTeam(Team $team, string $externalModelId): ?LlmModel
    {
        return LlmModel::query()
            ->with('provider')
            ->where('external_model_id', $externalModelId)
            ->where('is_active', true)
            ->whereHas('entitlements', function ($query) use ($team) {
                $query->where('team_id', $team->id)
                    ->where('is_enabled', true);
            })
            ->whereHas('provider', function ($query) use ($team) {
                $query->where('is_active', true)
                    ->whereHas('entitlements', function ($entitlements) use ($team) {
                        $entitlements->where('team_id', $team->id)
                            ->where('is_enabled', true);
                    });
            })
            ->first();
    }

    /**
     * @param  array<string, mixed>  $headers
     * @param  array<string, mixed>  $providerPayload
     */
    protected function forwardAsJson(
        Team $team,
        ApiKey $apiKey,
        LlmProvider $provider,
        LlmModel $model,
        string $incomingProtocol,
        string $providerProtocol,
        string $endpoint,
        string $url,
        array $headers,
        array $providerPayload,
        string $traceId,
        int $inputEstimate,
        ?string $idempotencyCacheKey = null,
        ?string $payloadHash = null,
    ): Response {
        $startedAt = microtime(true);

        try {
            $response = $this->sendWithRetry($headers, $url, $providerPayload, false);
        } catch (ConnectionException $exception) {
            $this->registerProviderFailure($provider);

            $this->recordApiRequestUsage->handle(
                team: $team,
                protocol: $incomingProtocol,
                endpoint: $endpoint,
                tokenInput: $inputEstimate,
                tokenOutput: 0,
                statusCode: 504,
                latencyMs: (int) round((microtime(true) - $startedAt) * 1000),
                errorCode: 'provider_timeout',
                errorMessage: $exception->getMessage(),
                traceId: $traceId,
                apiKey: $apiKey,
                provider: $provider,
                llmModel: $model,
                enforceQuota: false,
            );

            return $this->jsonWithTrace(
                $this->protocolTransformer->errorPayload($incomingProtocol, 'Provider timeout.', 'provider_timeout', 'api_error'),
                504,
                $traceId,
            );
        }

        if ($response->status() >= 500) {
            $this->registerProviderFailure($provider);
        } else {
            $this->registerProviderSuccess($provider);
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

        $adapted = $this->protocolTransformer->adaptResponse($body, $incomingProtocol, $providerProtocol, $model->external_model_id);
        $usage = $this->protocolTransformer->extractUsage($adapted, $incomingProtocol);
        $toolCallsCount = $this->protocolTransformer->extractToolCallsCount($adapted, $incomingProtocol);

        $this->recordApiRequestUsage->handle(
            team: $team,
            protocol: $incomingProtocol,
            endpoint: $endpoint,
            tokenInput: $usage['input'] > 0 ? $usage['input'] : $inputEstimate,
            tokenOutput: $usage['output'],
            isStreaming: false,
            toolCallsCount: $toolCallsCount,
            statusCode: $status,
            latencyMs: (int) round((microtime(true) - $startedAt) * 1000),
            errorCode: $status >= 400 ? (string) data_get($adapted, 'error.code', 'provider_error') : null,
            errorMessage: $status >= 400 ? (string) data_get($adapted, 'error.message', '') : null,
            traceId: $traceId,
            apiKey: $apiKey,
            provider: $provider,
            llmModel: $model,
            enforceQuota: false,
        );

        if ($idempotencyCacheKey && $payloadHash) {
            Cache::put($idempotencyCacheKey, [
                'payload_hash' => $payloadHash,
                'status' => $status,
                'body' => $adapted,
            ], now()->addSeconds((int) config('services.llm_gateway.idempotency_ttl_seconds', 300)));
        }

        return $this->jsonWithTrace($adapted, $status, $traceId);
    }

    /**
     * @param  array<string, mixed>  $headers
     * @param  array<string, mixed>  $providerPayload
     */
    protected function streamToClient(
        Team $team,
        ApiKey $apiKey,
        LlmProvider $provider,
        LlmModel $model,
        string $protocol,
        string $endpoint,
        string $url,
        array $headers,
        array $providerPayload,
        string $traceId,
        int $inputEstimate,
        string $providerProtocol,
    ): Response {
        $startedAt = microtime(true);

        try {
            $response = $this->sendWithRetry($headers, $url, $providerPayload, true);
        } catch (ConnectionException $exception) {
            $this->registerProviderFailure($provider);

            $this->recordApiRequestUsage->handle(
                team: $team,
                protocol: $protocol,
                endpoint: $endpoint,
                tokenInput: $inputEstimate,
                tokenOutput: 0,
                isStreaming: true,
                statusCode: 504,
                latencyMs: (int) round((microtime(true) - $startedAt) * 1000),
                errorCode: 'provider_timeout',
                errorMessage: $exception->getMessage(),
                traceId: $traceId,
                apiKey: $apiKey,
                provider: $provider,
                llmModel: $model,
                enforceQuota: false,
            );

            return $this->jsonWithTrace(
                $this->protocolTransformer->errorPayload($protocol, 'Provider timeout.', 'provider_timeout', 'api_error'),
                504,
                $traceId,
            );
        }

        if ($response->status() >= 500) {
            $this->registerProviderFailure($provider);
        } else {
            $this->registerProviderSuccess($provider);
        }

        $status = $response->status();
        $psrBody = $response->toPsrResponse()->getBody();

        $this->recordApiRequestUsage->handle(
            team: $team,
            protocol: $protocol,
            endpoint: $endpoint,
            tokenInput: $inputEstimate,
            tokenOutput: 0,
            isStreaming: true,
            statusCode: $status,
            latencyMs: (int) round((microtime(true) - $startedAt) * 1000),
            errorCode: $status >= 400 ? 'provider_error' : null,
            traceId: $traceId,
            apiKey: $apiKey,
            provider: $provider,
            llmModel: $model,
            enforceQuota: false,
        );

        return response()->stream(function () use ($psrBody, $protocol, $providerProtocol, $model): void {
            $buffer = '';

            while (! $psrBody->eof()) {
                $buffer .= $psrBody->read(8192);

                while (($separatorPosition = strpos($buffer, "\n\n")) !== false) {
                    $frame = substr($buffer, 0, $separatorPosition);
                    $buffer = substr($buffer, $separatorPosition + 2);

                    $adaptedFrame = $this->protocolTransformer->adaptStreamingFrame($frame, $protocol, $providerProtocol, $model->external_model_id);

                    if ($adaptedFrame !== '') {
                        echo $adaptedFrame;
                        flush();
                    }
                }
            }

            if ($buffer !== '') {
                $adaptedFrame = $this->protocolTransformer->adaptStreamingFrame($buffer, $protocol, $providerProtocol, $model->external_model_id);

                if ($adaptedFrame !== '') {
                    echo $adaptedFrame;
                    flush();
                }
            }
        }, $status, [
            'Content-Type' => $response->header('Content-Type', 'text/event-stream'),
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
            'X-Trace-Id' => $traceId,
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, string>  $headers
     */
    protected function jsonWithTrace(array $payload, int $status, string $traceId, array $headers = []): Response
    {
        return response()->json($payload, $status, array_merge($headers, [
            'X-Trace-Id' => $traceId,
        ]));
    }

    /**
     * @return array<string, string>
     */
    protected function providerHeaders(LlmProvider $provider, string $providerProtocol): array
    {
        $resolvedSecret = $this->resolveProviderSecret->handle($provider->secret_ref);

        $headers = [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ];

        if ($provider->auth_mode === 'bearer' && $resolvedSecret) {
            $headers['Authorization'] = 'Bearer '.$resolvedSecret;
        }

        if ($provider->auth_mode === 'header' && $resolvedSecret) {
            $headerName = (string) data_get($provider->options, 'auth_header', 'x-api-key');
            $headers[$headerName] = $resolvedSecret;
        }

        if ($providerProtocol === 'anthropic') {
            $headers['anthropic-version'] = (string) config('services.llm_gateway.anthropic_version', '2023-06-01');
        }

        foreach ((array) data_get($provider->options, 'headers', []) as $name => $value) {
            if (is_string($name) && is_string($value)) {
                $headers[$name] = $value;
            }
        }

        return $headers;
    }

    protected function providerEndpoint(LlmProvider $provider, string $providerProtocol, string $incomingEndpoint): string
    {
        if ($providerProtocol === 'anthropic') {
            return (string) data_get($provider->options, 'endpoints.messages', '/v1/messages');
        }

        if ($incomingEndpoint === '/v1/responses') {
            return (string) data_get($provider->options, 'endpoints.responses', '/v1/responses');
        }

        return (string) data_get($provider->options, 'endpoints.chat', '/v1/chat/completions');
    }

    /**
     * @param  array<string, string>  $headers
     * @param  array<string, mixed>  $payload
     */
    protected function sendWithRetry(array $headers, string $url, array $payload, bool $stream): HttpResponse
    {
        $attempts = max(1, (int) config('services.llm_gateway.retry_attempts', 2));
        $backoffMs = max(0, (int) config('services.llm_gateway.retry_backoff_ms', 150));
        $lastConnectionException = null;

        for ($attempt = 1; $attempt <= $attempts; $attempt++) {
            try {
                $request = Http::withHeaders($headers)
                    ->timeout(config('services.llm_gateway.timeout_seconds', 120));

                if ($stream) {
                    $request = $request->withOptions(['stream' => true]);
                }

                $response = $request->send('POST', $url, ['json' => $payload]);

                if ($response->status() < 500 || $attempt === $attempts) {
                    return $response;
                }
            } catch (ConnectionException $exception) {
                $lastConnectionException = $exception;

                if ($attempt === $attempts) {
                    throw $exception;
                }
            }

            if ($backoffMs > 0) {
                usleep($backoffMs * 1000);
            }
        }

        if ($lastConnectionException instanceof ConnectionException) {
            throw $lastConnectionException;
        }

        throw new ConnectionException('Gateway request failed after retries.');
    }

    protected function idempotencyCacheKey(Team $team, ApiKey $apiKey, string $endpoint, string $idempotencyKey): string
    {
        return sprintf(
            'gateway:idempotency:%d:%d:%s:%s',
            $team->id,
            $apiKey->id,
            md5($endpoint),
            sha1($idempotencyKey),
        );
    }

    protected function isProviderCircuitOpen(LlmProvider $provider): bool
    {
        $openUntil = Cache::get($this->providerCircuitOpenUntilCacheKey($provider));

        return is_numeric($openUntil) && (int) $openUntil > now()->timestamp;
    }

    protected function registerProviderFailure(LlmProvider $provider): void
    {
        $failuresKey = $this->providerFailureCountCacheKey($provider);
        $cooldownSeconds = max(1, (int) config('services.llm_gateway.circuit_cooldown_seconds', 60));
        $threshold = max(1, (int) config('services.llm_gateway.circuit_failure_threshold', 5));

        $currentFailures = Cache::increment($failuresKey);

        if ($currentFailures === 1) {
            Cache::put($failuresKey, 1, now()->addSeconds($cooldownSeconds));
        }

        if ($currentFailures >= $threshold) {
            Cache::put($this->providerCircuitOpenUntilCacheKey($provider), now()->addSeconds($cooldownSeconds)->timestamp, now()->addSeconds($cooldownSeconds));
        }
    }

    protected function registerProviderSuccess(LlmProvider $provider): void
    {
        Cache::forget($this->providerFailureCountCacheKey($provider));
        Cache::forget($this->providerCircuitOpenUntilCacheKey($provider));
    }

    protected function providerFailureCountCacheKey(LlmProvider $provider): string
    {
        return sprintf('gateway:circuit:provider:%d:failures', $provider->id);
    }

    protected function providerCircuitOpenUntilCacheKey(LlmProvider $provider): string
    {
        return sprintf('gateway:circuit:provider:%d:open_until', $provider->id);
    }
}
