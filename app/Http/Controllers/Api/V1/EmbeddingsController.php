<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AiModel;
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
                [
                    'error' => [
                        'type' => 'invalid_request_error',
                        'message' => 'The model field is required.',
                        'code' => 'missing_model',
                    ],
                ],
                422,
            );
        }

        $input = $payload['input'] ?? null;

        if ($input === null || $input === '') {
            return response()->json(
                [
                    'error' => [
                        'type' => 'invalid_request_error',
                        'message' => 'The input field is required.',
                        'code' => 'missing_input',
                    ],
                ],
                422,
            );
        }

        $aiModel = AiModel::query()
            ->with('aiProvider')
            ->where('external_model_id', $requestedModel)
            ->where('is_active', true)
            ->whereHas('aiProvider', fn ($query) => $query->where('is_active', true))
            ->firstOrFail();

        $aiProvider = $aiModel->aiProvider;

        $endpoint = (string) data_get($aiProvider->options, 'endpoints.embeddings', '/v1/embeddings');
        $url = rtrim($aiProvider->base_url, '/').$endpoint;

        $providerPayload = collect([
            'model' => $aiModel->external_model_id,
            'input' => $input,
            'encoding_format' => $payload['encoding_format'] ?? null,
            'dimensions' => $payload['dimensions'] ?? null,
        ])
            ->reject(fn ($value) => $value === null)
            ->all();

        $headers = $request->headers->all();
        unset($headers['host']);

        if ($aiProvider->secret_ref) {
            $headers['Authorization'] = $aiProvider->secret_ref;
        }

        foreach ((array) data_get($aiProvider->options, 'headers', []) as $name => $value) {
            if (is_string($name) && is_string($value)) {
                $headers[$name] = $value;
            }
        }

        $response = Http::withHeaders($headers)
            ->timeout((int) config('services.llm_gateway.timeout_seconds', 120))
            ->send('POST', $url, ['json' => $providerPayload]);

        return response(
            $response->body(),
            $response->status(),
            ['Content-Type' => $response->header('Content-Type') ?: 'application/json'],
        );
    }
}
