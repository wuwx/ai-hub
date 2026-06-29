<?php

namespace App\Http\Middleware;

use App\Models\ApiKey;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateApiKey
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $traceId = (string) $request->header('X-Trace-Id', Str::uuid()->toString());
        $request->attributes->set('gateway.trace_id', $traceId);

        $plainTextKey = $request->bearerToken() ?: $request->header('x-api-key');

        if (! is_string($plainTextKey) || $plainTextKey === '') {
            return $this->unauthorized('Missing API key.', $traceId);
        }

        $apiKey = ApiKey::query()
            ->with('team')
            ->where('key_hash', ApiKey::hashPlainTextKey($plainTextKey))
            ->whereNull('revoked_at')
            ->where(function ($query) {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->first();

        if (! $apiKey || ! $apiKey->team) {
            return $this->unauthorized('Invalid API key.', $traceId);
        }

        $apiKey->forceFill(['last_used_at' => now()])->save();

        $request->attributes->set('gateway.api_key', $apiKey);
        $request->attributes->set('gateway.team', $apiKey->team);

        return $next($request);
    }

    protected function unauthorized(string $message, string $traceId): JsonResponse
    {
        return response()->json([
            'error' => [
                'type' => 'authentication_error',
                'message' => $message,
            ],
        ], 401, [
            'X-Trace-Id' => $traceId,
        ]);
    }
}
