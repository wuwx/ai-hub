<?php

namespace App\Http\Middleware;

use App\Models\AiModel;
use App\Models\AiProvider;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolve the upstream provider for an incoming gateway request.
 *
 * Looks up the AiModel referenced by the request's `model` field, loads its
 * AiProvider, and stores it on the request as the `gateway.provider` attribute
 * so downstream code (e.g. the gateway target closure) can reuse it without
 * re-querying. Provider-specific headers (Authorization, options.headers)
 * are merged into the incoming request, allowing the gateway controller to
 * forward them transparently.
 */
class ResolveGatewayProvider
{
    public function handle(Request $request, Closure $next): Response
    {
        $aiModel = AiModel::query()
            ->with('aiProvider')
            ->where('external_model_id', (string) $request->json('model', ''))
            ->where('is_active', true)
            ->whereHas('aiProvider', fn ($query) => $query->where('is_active', true))
            ->firstOrFail();

        /** @var AiProvider $aiProvider */
        $aiProvider = $aiModel->aiProvider;

        $request->attributes->set('gateway.provider', $aiProvider);

        if ($aiProvider->secret_ref) {
            $request->headers->set('Authorization', $aiProvider->secret_ref);
        }

        foreach ((array) data_get($aiProvider->options, 'headers', []) as $name => $value) {
            if (is_string($name) && is_string($value)) {
                $request->headers->set($name, $value);
            }
        }

        return $next($request);
    }
}
