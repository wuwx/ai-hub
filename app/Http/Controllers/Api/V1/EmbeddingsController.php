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

class EmbeddingsController extends Controller
{
    /**
     * Handle an OpenAI-compatible embeddings request.
     *
     * Unlike chat completions, embeddings are always non-streaming and only
     * consume input tokens. The response is returned as-is from the provider.
     */
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

        $input = $payload['input'] ?? null;

        if ($input === null || $input === '') {
            return response()->json(
                $this->errorPayload('The input field is required.', 'missing_input'),
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

        $headers = $this->providerHeaders($provider);
        $endpoint = (string) data_get($provider->options, 'endpoints.embeddings', '/v1/embeddings');
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
            $response = $this->sendWithRetry($headers, $url, $providerPayload);
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
    protected function sendWithRetry(array $headers, string $url, array $payload): HttpResponse
    {
        $attempts = max(1, (int) config('services.llm_gateway.retry_attempts', 2));
        $backoffMs = max(0, (int) config('services.llm_gateway.retry_backoff_ms', 150));

        return Http::withHeaders($headers)
            ->timeout((int) config('services.llm_gateway.timeout_seconds', 120))
            ->retry(
                $attempts,
                $backoffMs,
                fn ($exception) => $exception instanceof ConnectionException
                    || ($exception instanceof RequestException && $exception->response->status() >= 500),
            )
            ->send('POST', $url, ['json' => $payload]);
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
