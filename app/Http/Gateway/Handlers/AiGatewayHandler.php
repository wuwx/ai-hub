<?php

namespace App\Http\Gateway\Handlers;

use App\Models\AiProvider;
use Illuminate\Http\Request;
use Wuwx\LaravelGateway\GatewayHandler;

/**
 * Build the gateway options for any of the AI provider proxy endpoints by
 * mapping the matched route URI to a provider endpoint key.
 *
 * Usage:
 *   Gateway::any('chat/completions', AiGatewayHandler::class);
 *   Gateway::any('embeddings',       AiGatewayHandler::class);
 *   Gateway::any('responses',        AiGatewayHandler::class);
 *   Gateway::any('messages',         AiGatewayHandler::class);
 *
 * Each route shares one handler; the per-route differences are encoded in
 * {@see $endpoints} below, keyed by the URI suffix.
 */
class AiGatewayHandler extends GatewayHandler
{
    /**
     * Per-endpoint configuration.
     *
     * @var array<string, array{endpoint: string, default: string, streaming?: bool, timeout?: int}>
     */
    private array $endpoints = [
        'chat/completions' => [
            'endpoint' => 'endpoints.chat',
            'default' => '/v1/chat/completions',
        ],
        'embeddings' => [
            'endpoint' => 'endpoints.embeddings',
            'default' => '/v1/embeddings',
            'streaming' => false,
            'timeout' => 120,
        ],
        'responses' => [
            'endpoint' => 'endpoints.responses',
            'default' => '/v1/responses',
            'timeout' => 120,
        ],
        'messages' => [
            'endpoint' => 'endpoints.messages',
            'default' => '/v1/messages',
            'timeout' => 120,
        ],
    ];

    /**
     * Build the gateway options for the matched route.
     *
     * @return array<string, mixed>
     */
    public function getOptions(): array
    {
        $request = request();
        $uri = $this->resolveUri($request);

        $config = $this->endpoints[$uri] ?? throw new \RuntimeException(
            "No gateway configuration for URI: {$uri}",
        );

        /** @var AiProvider $provider */
        $provider = $request->attributes->get('gateway.provider');

        $baseUri = rtrim((string) $provider->base_url, '/')
            .(string) data_get($provider->options, $config['endpoint'], $config['default']);

        $options = ['base_uri' => $baseUri];

        if (($config['streaming'] ?? true) === false) {
            $options['streaming'] = false;
        }

        if (isset($config['timeout'])) {
            $options['timeout'] = (int) config(
                'services.llm_gateway.timeout_seconds',
                $config['timeout'],
            );
        }

        return $options;
    }

    /**
     * Resolve the URI suffix (e.g. "chat/completions") from the matched route.
     */
    private function resolveUri(Request $request): string
    {
        $uri = $request->route()?->uri() ?? '';
        $prefix = 'api/v1/';

        return str_starts_with($uri, $prefix)
            ? substr($uri, strlen($prefix))
            : $uri;
    }
}
