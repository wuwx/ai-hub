<?php

namespace App\Http\Middleware;

use App\Models\ApiKey;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class ThrottleApiKeyRequests
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $maxAttemptsPerMinute = (int) config('services.llm_gateway.api_key_rate_limit_per_minute', 120);

        if ($maxAttemptsPerMinute <= 0) {
            return $next($request);
        }

        /** @var ApiKey|null $apiKey */
        $apiKey = $request->attributes->get('gateway.api_key');
        $traceId = (string) ($request->attributes->get('gateway.trace_id') ?: Str::uuid()->toString());
        $request->attributes->set('gateway.trace_id', $traceId);

        if (! $apiKey) {
            return $next($request);
        }

        $decaySeconds = max(1, (int) config('services.llm_gateway.api_key_rate_limit_decay_seconds', 60));
        $rateLimitKey = sprintf('llm_gateway:api_key:%d', $apiKey->id);

        if (RateLimiter::tooManyAttempts($rateLimitKey, $maxAttemptsPerMinute)) {
            return $this->tooManyRequests(RateLimiter::availableIn($rateLimitKey), $traceId);
        }

        RateLimiter::hit($rateLimitKey, $decaySeconds);

        return $next($request);
    }

    protected function tooManyRequests(int $retryAfterSeconds, string $traceId): JsonResponse
    {
        return response()->json([
            'error' => [
                'type' => 'rate_limit_error',
                'code' => 'too_many_requests',
                'message' => 'API rate limit exceeded. Please retry later.',
                'retry_after_seconds' => max(1, $retryAfterSeconds),
            ],
        ], 429, [
            'X-Trace-Id' => $traceId,
        ]);
    }
}
