<?php

namespace App\Jobs;

use App\Models\WebhookDelivery;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class RetryWebhookDeliveryJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    public int $timeout = 30;

    public function __construct(
        public int $deliveryId,
    ) {
        //
    }

    public function handle(): void
    {
        $delivery = WebhookDelivery::with('endpoint')->find($this->deliveryId);

        if (! $delivery || $delivery->succeeded) {
            return;
        }

        $endpoint = $delivery->endpoint;

        if (! $endpoint || ! $endpoint->is_active) {
            return;
        }

        // Max 5 attempts total
        if ($delivery->attempt_count >= 5) {
            Log::info('webhook.retry.max_attempts_reached', [
                'delivery_id' => $delivery->id,
                'endpoint_id' => $endpoint->id,
                'event' => $delivery->event,
            ]);

            return;
        }

        $body = json_encode($delivery->payload, JSON_UNESCAPED_UNICODE);
        $signature = hash_hmac('sha256', $body, (string) $endpoint->secret);
        $startedAt = microtime(true);

        try {
            $response = Http::timeout(10)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'X-Webhook-Event' => $delivery->event,
                    'X-Webhook-Signature' => 'sha256='.$signature,
                ])
                ->post($endpoint->url, $delivery->payload);

            $latencyMs = (int) round((microtime(true) - $startedAt) * 1000);
            $succeeded = $response->successful();

            $delivery->update([
                'response_status_code' => $response->status(),
                'response_body' => mb_substr($response->body(), 0, 10000),
                'succeeded' => $succeeded,
                'latency_ms' => $latencyMs,
                'attempt_count' => $delivery->attempt_count + 1,
                'next_retry_at' => $succeeded ? null : $this->nextRetryAt($delivery->attempt_count + 1),
                'error' => null,
            ]);

            if ($succeeded) {
                $endpoint->update([
                    'last_triggered_at' => now(),
                    'failure_count' => 0,
                ]);

                return;
            }

            // Schedule next retry
            $this->scheduleRetry($delivery);
        } catch (\Throwable $exception) {
            $latencyMs = (int) round((microtime(true) - $startedAt) * 1000);

            $delivery->update([
                'response_status_code' => null,
                'response_body' => null,
                'succeeded' => false,
                'latency_ms' => $latencyMs,
                'attempt_count' => $delivery->attempt_count + 1,
                'next_retry_at' => $this->nextRetryAt($delivery->attempt_count + 1),
                'error' => $exception->getMessage(),
            ]);

            $this->scheduleRetry($delivery);
        }
    }

    protected function scheduleRetry(WebhookDelivery $delivery): void
    {
        if ($delivery->attempt_count < 5) {
            $delay = $this->nextRetryAt($delivery->attempt_count);
            self::dispatch($delivery->id)->delay($delay);
        }
    }

    protected function nextRetryAt(int $attempt): \DateTimeInterface
    {
        // Exponential backoff: 30s, 2m, 10m, 30m, 1h
        $delays = [30, 120, 600, 1800, 3600];

        $delaySeconds = $delays[min($attempt - 1, count($delays) - 1)] ?? 3600;

        return now()->addSeconds($delaySeconds);
    }
}
