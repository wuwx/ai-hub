<?php

namespace App\Http\Middleware;

use App\Models\ApiKey;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class EnforceConcurrentRequestLimit
{
    /**
     * Handle an incoming request.
     *
     * Enforces a per-team concurrent in-flight request limit using an atomic
     * cache counter. This prevents a single team from opening hundreds of
     * simultaneous streaming connections that consume upstream tokens.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $maxConcurrent = (int) config('services.llm_gateway.max_concurrent_per_team', 50);

        if ($maxConcurrent <= 0) {
            return $next($request);
        }

        /** @var ApiKey|null $apiKey */
        $apiKey = $request->attributes->get('gateway.api_key');
        $traceId = (string) ($request->attributes->get('gateway.trace_id') ?: Str::uuid()->toString());
        $request->attributes->set('gateway.trace_id', $traceId);

        if (! $apiKey) {
            return $next($request);
        }

        $team = $apiKey->team;

        if (! $team) {
            return $next($request);
        }

        $cacheKey = sprintf('gateway:concurrent:%d', $team->id);
        $current = (int) Cache::get($cacheKey, 0);

        if ($current >= $maxConcurrent) {
            return $this->tooManyConcurrent($maxConcurrent, $traceId);
        }

        // Increment concurrent counter
        $newCount = Cache::increment($cacheKey);

        // Set a TTL as a safety net so a crashed request doesn't permanently hold a slot.
        if ($newCount === 1) {
            Cache::put($cacheKey, 1, now()->addMinutes(10));
        }

        try {
            $response = $next($request);
        } finally {
            // Decrement counter when request completes
            Cache::decrement($cacheKey);
        }

        return $response;
    }

    protected function tooManyConcurrent(int $maxConcurrent, string $traceId): JsonResponse
    {
        return response()->json([
            'error' => [
                'type' => 'rate_limit_error',
                'code' => 'too_many_concurrent_requests',
                'message' => sprintf('Too many concurrent requests. Maximum %d in-flight requests allowed. Please retry after a request completes.', $maxConcurrent),
            ],
        ], 429, [
            'X-Trace-Id' => $traceId,
            'Retry-After' => '5',
        ]);
    }
}
