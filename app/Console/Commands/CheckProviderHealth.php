<?php

namespace App\Console\Commands;

use App\Actions\Gateway\ResolveProviderSecret;
use App\Models\LlmProvider;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

#[Signature('gateway:check-provider-health')]
#[Description('Ping each active LLM provider and record its health status.')]
class CheckProviderHealth extends Command
{
    public function __construct(private readonly ResolveProviderSecret $resolveProviderSecret)
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $providers = LlmProvider::query()->where('is_active', true)->get();

        if ($providers->isEmpty()) {
            $this->info('No active providers to check.');

            return self::SUCCESS;
        }

        $timeout = (int) config('services.llm_gateway.timeout_seconds', 120);
        $healthTimeout = min(10, max(3, (int) ($timeout / 10)));

        $healthy = 0;
        $unhealthy = 0;

        foreach ($providers as $provider) {
            $this->checkProvider($provider, $healthTimeout)
                ? $healthy++
                : $unhealthy++;
        }

        $this->info(sprintf('Health check complete: %d healthy, %d unhealthy.', $healthy, $unhealthy));

        return self::SUCCESS;
    }

    protected function checkProvider(LlmProvider $provider, int $timeout): bool
    {
        $url = $this->healthCheckUrl($provider);
        $headers = $this->healthCheckHeaders($provider);

        try {
            $response = Http::withHeaders($headers)
                ->timeout($timeout)
                ->get($url);

            $ok = $response->status() >= 200 && $response->status() < 500;
        } catch (ConnectionException $exception) {
            $provider->update([
                'last_health_status' => 'unhealthy',
                'last_health_checked_at' => now(),
                'last_health_error' => $exception->getMessage(),
            ]);

            Log::warning('gateway.provider.health_check_failed', [
                'provider_id' => $provider->id,
                'provider_slug' => $provider->slug,
                'url' => $url,
                'error' => $exception->getMessage(),
            ]);

            $this->line("  <fg=red>✗</> {$provider->slug}: {$exception->getMessage()}");

            return false;
        }

        if (! $ok) {
            $error = "HTTP {$response->status()}";

            $provider->update([
                'last_health_status' => 'unhealthy',
                'last_health_checked_at' => now(),
                'last_health_error' => $error,
            ]);

            Log::warning('gateway.provider.health_check_failed', [
                'provider_id' => $provider->id,
                'provider_slug' => $provider->slug,
                'url' => $url,
                'status' => $response->status(),
            ]);

            $this->line("  <fg=red>✗</> {$provider->slug}: {$error}");

            return false;
        }

        $provider->update([
            'last_health_status' => 'healthy',
            'last_health_checked_at' => now(),
            'last_health_error' => null,
        ]);

        $this->line("  <fg=green>✓</> {$provider->slug}");

        return true;
    }

    protected function healthCheckUrl(LlmProvider $provider): string
    {
        $healthPath = (string) data_get($provider->options, 'health_endpoint', '/v1/models');

        return rtrim($provider->base_url, '/').$healthPath;
    }

    /**
     * @return array<string, string>
     */
    protected function healthCheckHeaders(LlmProvider $provider): array
    {
        $headers = [
            'Accept' => 'application/json',
        ];

        $secret = $this->resolveProviderSecret->handle($provider->secret_ref);

        if ($provider->auth_mode === 'bearer' && $secret) {
            $headers['Authorization'] = 'Bearer '.$secret;
        }

        if ($provider->auth_mode === 'header' && $secret) {
            $headerName = (string) data_get($provider->options, 'auth_header', 'x-api-key');
            $headers[$headerName] = $secret;
        }

        return $headers;
    }
}
