<?php

namespace App\Actions\Webhooks;

use App\Models\Team;
use App\Models\TeamWebhookEndpoint;
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

            $this->send($endpoint, $event, $body);
        }
    }

    protected function send(TeamWebhookEndpoint $endpoint, string $event, string $body): void
    {
        $signature = hash_hmac('sha256', $body, (string) $endpoint->secret);

        try {
            $response = Http::timeout(10)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'X-Webhook-Event' => $event,
                    'X-Webhook-Signature' => 'sha256='.$signature,
                ])
                ->post($endpoint->url, json_decode($body, true));

            if ($response->successful()) {
                $endpoint->update([
                    'last_triggered_at' => now(),
                    'failure_count' => 0,
                ]);

                return;
            }

            $this->recordFailure($endpoint, $response->status());
        } catch (\Throwable $exception) {
            Log::warning('webhook.dispatch.failed', [
                'webhook_id' => $endpoint->id,
                'url' => $endpoint->url,
                'event' => $event,
                'error' => $exception->getMessage(),
            ]);

            $this->recordFailure($endpoint, 0);
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
