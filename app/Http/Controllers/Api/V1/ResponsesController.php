<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AiModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpFoundation\Response;

class ResponsesController extends Controller
{
    public function __invoke(Request $request): Response
    {
        /** @var array<string, mixed> $payload */
        $payload = $request->json()->all();

        $aiModel = AiModel::query()
            ->with('aiProvider')
            ->where('external_model_id', (string) ($payload['model'] ?? ''))
            ->where('is_active', true)
            ->whereHas('aiProvider', fn ($query) => $query->where('is_active', true))
            ->firstOrFail();

        $aiProvider = $aiModel->aiProvider;

        if ($aiProvider->adapter_type === 'anthropic_compatible') {
            return response()->json(
                [
                    'error' => [
                        'type' => 'invalid_request_error',
                        'message' => 'The requested model is not available in the openai API format.',
                        'code' => 'protocol_mismatch',
                    ],
                ],
                422,
            );
        }

        $providerPayload = $payload;
        $providerPayload['model'] = $aiModel->external_model_id;

        $endpoint = (string) data_get($aiProvider->options, 'endpoints.responses', '/v1/responses');
        $url = rtrim($aiProvider->base_url, '/').$endpoint;

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
            ->withOptions(['stream' => true])
            ->send('POST', $url, ['json' => $providerPayload]);

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
                'Content-Type' => $response->header('Content-Type') ?: 'application/json',
                'Cache-Control' => 'no-cache',
                'X-Accel-Buffering' => 'no',
            ],
        );
    }
}
