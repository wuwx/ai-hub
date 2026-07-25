<?php

namespace App\Http\Middleware;

use App\Models\AiProvider;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Restrict a gateway route to providers with one of the allowed adapter_types.
 *
 * Run AFTER ResolveGatewayProvider so the resolved provider is available on
 * the request attributes. Returns a 422 with the OpenAI-style error envelope
 * (or Anthropic-style when `$errorFormat` is `anthropic`) when the resolved
 * provider's adapter_type is not in the allowed list.
 *
 * Usage in routes:
 *   ->middleware(EnsureAdapterType::class.':openai_compatible,openai')
 *   ->middleware(EnsureAdapterType::class.':anthropic_compatible,anthropic')
 */
class EnsureAdapterType
{
    public function handle(Request $request, Closure $next, string $allowedTypes, string $errorFormat = 'openai'): Response
    {
        /** @var AiProvider $aiProvider */
        $aiProvider = $request->attributes->get('gateway.provider');

        $allowed = explode(',', $allowedTypes);

        if (! in_array($aiProvider->adapter_type, $allowed, true)) {
            $api = $errorFormat === 'anthropic' ? 'anthropic' : 'openai';

            return $errorFormat === 'anthropic'
                ? response()->json([
                    'type' => 'error',
                    'error' => [
                        'type' => 'invalid_request_error',
                        'message' => "The requested model is not available in the {$api} API format.",
                        'code' => 'protocol_mismatch',
                    ],
                ], 422)
                : response()->json([
                    'error' => [
                        'type' => 'invalid_request_error',
                        'message' => "The requested model is not available in the {$api} API format.",
                        'code' => 'protocol_mismatch',
                    ],
                ], 422);
        }

        return $next($request);
    }
}
