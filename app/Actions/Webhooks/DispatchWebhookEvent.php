<?php

namespace App\Actions\Webhooks;

use App\Jobs\RetryWebhookDeliveryJob;
use App\Models\Team;
use App\Models\TeamWebhookEndpoint;
use App\Models\WebhookDelivery;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DispatchWebhookEvent
{
    /**
     * Dispatch an event to all active webhook endpoints subscribed to it.
     *
     * @param  array<string, mixed>  $data
     */
    public function handle(Team $team, string $event, array $data = []): void
    {
        $endpoints = TeamWebhookEndpoint::query()
            ->where('team_id', $team->id)
            ->where('is_active', true)
            ->get();

        if ($endpoints->isEmpty()) {
            return;
        }

        $payload = [
            'event' => $event,
            'timestamp' => now()->toIso8601String(),
            'data' => $data,
        ];

        $body = json_encode($payload, JSON_UNESCAPED_UNICODE);

        foreach ($endpoints as $endpoint) {
            if (! $endpoint->subscribesTo($event)) {
                continue;
            }

            $this->send($endpoint, $event, $body, $payload);
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function send(TeamWebhookEndpoint $endpoint, string $event, string $body, array $payload): void
    {
        $signature = hash_hmac('sha256', $body, (string) $endpoint->secret);
        $startedAt = microtime(true);

        try {
            $response = Http::timeout(10)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'X-Webhook-Event' => $event,
                    'X-Webhook-Signature' => 'sha256='.$signature,
                ])
                ->post($endpoint->url, json_decode($body, true));

            $latencyMs = (int) round((microtime(true) - $startedAt) * 1000);
            $succeeded = $response->successful();

            $delivery = WebhookDelivery::create([
                'team_webhook_endpoint_id' => $endpoint->id,
                'event' => $event,
                'payload' => $payload,
                'response_status_code' => $response->status(),
                'response_body' => mb_substr($response->body(), 0, 10000),
                'succeeded' => $succeeded,
                'latency_ms' => $latencyMs,
            ]);

            if ($succeeded) {
                $endpoint->update([
                    'last_triggered_at' => now(),
                    'failure_count' => 0,
                ]);

                return;
            }

            $this->recordFailure($endpoint, $response->status());

            // Schedule retry with exponential backoff
            $delivery->update(['next_retry_at' => now()->addSeconds(30)]);
            RetryWebhookDeliveryJob::dispatch($delivery->id)->delay(now()->addSeconds(30));
        } catch (\Throwable $exception) {
            $latencyMs = (int) round((microtime(true) - $startedAt) * 1000);

            $delivery = WebhookDelivery::create([
                'team_webhook_endpoint_id' => $endpoint->id,
                'event' => $event,
                'payload' => $payload,
                'response_status_code' => null,
                'response_body' => null,
                'succeeded' => false,
                'latency_ms' => $latencyMs,
                'error' => $exception->getMessage(),
            ]);

            Log::warning('webhook.dispatch.failed', [
                'webhook_id' => $endpoint->id,
                'url' => $endpoint->url,
                'event' => $event,
                'error' => $exception->getMessage(),
            ]);

            $this->recordFailure($endpoint, 0);

            // Schedule retry
            $delivery->update(['next_retry_at' => now()->addSeconds(30)]);
            RetryWebhookDeliveryJob::dispatch($delivery->id)->delay(now()->addSeconds(30));
        }
    }

    protected function recordFailure(TeamWebhookEndpoint $endpoint, int $statusCode): void
    {
        $failures = $endpoint->failure_count + 1;

        // Auto-disable after 10 consecutive failures to avoid hammering dead endpoints.
        $updates = [
            'last_triggered_at' => now(),
            'failure_count' => $failures,
        ];

        if ($failures >= 10) {
            $updates['is_active'] = false;
        }

        $endpoint->update($updates);
    }
}
