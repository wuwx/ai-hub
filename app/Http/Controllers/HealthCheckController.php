<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class HealthCheckController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $checks = [
            'database' => $this->checkDatabase(),
            'cache' => $this->checkCache(),
        ];

        $allHealthy = collect($checks)->every(fn (array $check) => $check['status'] === 'ok');

        return response()->json([
            'status' => $allHealthy ? 'ok' : 'degraded',
            'timestamp' => now()->toIso8601String(),
            'checks' => $checks,
        ], $allHealthy ? Response::HTTP_OK : Response::HTTP_SERVICE_UNAVAILABLE);
    }

    /**
     * @return array{status: string, latency_ms?: int, error?: string}
     */
    protected function checkDatabase(): array
    {
        $startedAt = microtime(true);

        try {
            DB::select('SELECT 1');

            return [
                'status' => 'ok',
                'latency_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            ];
        } catch (\Throwable $exception) {
            return [
                'status' => 'failed',
                'error' => $exception->getMessage(),
            ];
        }
    }

    /**
     * @return array{status: string, latency_ms?: int, error?: string}
     */
    protected function checkCache(): array
    {
        $startedAt = microtime(true);

        try {
            Cache::store()->put('health:check', true, 10);
            Cache::store()->get('health:check');
            Cache::store()->forget('health:check');

            return [
                'status' => 'ok',
                'latency_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            ];
        } catch (\Throwable $exception) {
            return [
                'status' => 'failed',
                'error' => $exception->getMessage(),
            ];
        }
    }
}
