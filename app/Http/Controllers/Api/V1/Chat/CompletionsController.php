<?php

namespace App\Http\Controllers\Api\V1\Chat;

use App\Http\Controllers\Controller;
use App\Models\AiModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpFoundation\Response;

class CompletionsController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $aiModel = AiModel::query()
            ->with('aiProvider')
            ->where('external_model_id', (string) $request->json('model', ''))
            ->where('is_active', true)
            ->whereHas('aiProvider', fn ($query) => $query->where('is_active', true))
            ->firstOrFail();

        $aiProvider = $aiModel->aiProvider;

        $url = rtrim($aiProvider->base_url, '/')
            .(string) data_get($aiProvider->options, 'endpoints.chat', '/v1/chat/completions');

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
            ->withOptions(['stream' => true])
            ->send('POST', $url, ['body' => $request->getContent()]);

        return response()->stream(
            function () use ($response): void {
                $body = $response->toPsrResponse()->getBody();

                while (! $body->eof()) {
                    echo $body->read(8192);
                    flush();
                }
            },
            $response->status(),
            $response->headers(),
        );
    }
}
