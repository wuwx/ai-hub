<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\LlmModel;
use App\Models\LlmProvider;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response as HttpResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpFoundation\Response;

class ResponsesController extends Controller
{
    public function __invoke(Request $request): Response
    {
        /** @var array<string, mixed> $payload */
        $payload = $request->json()->all();

        $requestedModel = (string) ($payload['model'] ?? '');

        if ($requestedModel === '') {
            return response()->json(
                $this->errorPayload('The model field is required.', 'missing_model'),
                422,
            );
        }

        $model = $this->resolveModel($requestedModel);

        if (! $model) {
            return response()->json(
                $this->errorPayload('Model is unavailable for this user.', 'model_unavailable'),
                422,
            );
        }

        $provider = $model->provider;

        if ($this->providerProtocol($provider->adapter_type) !== 'openai') {
            return response()->json(
                $this->errorPayload(
                    'The requested model is not available in the openai API format.',
                    'protocol_mismatch',
                ),
                422,
            );
        }

        $providerPayload = $payload;
        $providerPayload['model'] = $model->external_model_id;

        $isStreaming = (bool) ($payload['stream'] ?? false);

        $headers = $this->providerHeaders($provider);
        $endpoint = (string) data_get($provider->options, 'endpoints.responses', '/v1/responses');
        $url = rtrim($provider->base_url, '/').$endpoint;

        if ($isStreaming) {
            return $this->streamToClient($url, $headers, $providerPayload);
        }

        return $this->forwardResponse($url, $headers, $providerPayload);
    }

    protected function resolveModel(string $externalModelId): ?LlmModel
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
    protected function forwardResponse(string $url, array $headers, array $providerPayload): Response
    {
        try {
            $response = $this->sendWithRetry($headers, $url, $providerPayload, false);
        } catch (ConnectionException) {
            return response()->json(
                $this->errorPayload('Provider timeout.', 'provider_timeout', 'api_error'),
                504,
            );
        }

        return response(
            $response->body(),
            $response->status(),
            ['Content-Type' => $response->header('Content-Type') ?: 'application/json'],
        );
    }

    /**
     * @param  array<string, string>  $headers
     * @param  array<string, mixed>  $providerPayload
     */
    protected function streamToClient(string $url, array $headers, array $providerPayload): Response
    {
        try {
            $response = $this->sendWithRetry($headers, $url, $providerPayload, true);
        } catch (ConnectionException) {
            return response()->json(
                $this->errorPayload('Provider timeout.', 'provider_timeout', 'api_error'),
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
    protected function providerHeaders(LlmProvider $provider): array
    {
        $headers = [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ];

        if ($provider->auth_mode === 'bearer' && $provider->secret_ref) {
            $headers['Authorization'] = 'Bearer '.$provider->secret_ref;
        }

        if ($provider->auth_mode === 'header' && $provider->secret_ref) {
            $headerName = (string) data_get($provider->options, 'auth_header', 'x-api-key');
            $headers[$headerName] = $provider->secret_ref;
        }

        foreach ((array) data_get($provider->options, 'headers', []) as $name => $value) {
            if (is_string($name) && is_string($value)) {
                $headers[$name] = $value;
            }
        }

        return $headers;
    }

    /**
     * @param  array<string, string>  $headers
     * @param  array<string, mixed>  $payload
     */
    protected function sendWithRetry(array $headers, string $url, array $payload, bool $stream): HttpResponse
    {
        $attempts = max(1, (int) config('services.llm_gateway.retry_attempts', 2));
        $backoffMs = max(0, (int) config('services.llm_gateway.retry_backoff_ms', 150));

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

    protected function providerProtocol(string $adapterType): string
    {
        return match ($adapterType) {
            'anthropic_compatible' => 'anthropic',
            default => 'openai',
        };
    }

    /**
     * @return array<string, mixed>
     */
    protected function errorPayload(
        string $message,
        string $code,
        string $type = 'invalid_request_error',
    ): array {
        return [
            'error' => [
                'type' => $type,
                'message' => $message,
                'code' => $code,
            ],
        ];
    }
}
