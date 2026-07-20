<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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

        // Accept the key via either the standard Authorization: Bearer header
        // or the alternate x-api-key header documented in the API reference.
        $key = $request->bearerToken() ?: $request->header('x-api-key');

        if (! is_string($key) || $key === '') {
            return $this->unauthorized('Missing API key.', $traceId);
        }

        if ($request->header('x-api-key') && ! $request->bearerToken()) {
            $request->headers->set('Authorization', 'Bearer '.$key);
        }

        Auth::shouldUse('sanctum');
        $user = auth()->user();

        if (! $user) {
            return $this->unauthorized('Invalid API key.', $traceId);
        }

        $token = $user->currentAccessToken();

        if ($token->expires_at !== null && $token->expires_at->isPast()) {
            return $this->unauthorized('Expired API key.', $traceId);
        }

        $token->forceFill(['last_used_at' => now()])->save();

        $request->attributes->set('gateway.api_key', $token);
        $request->attributes->set('gateway.user', $user);

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
