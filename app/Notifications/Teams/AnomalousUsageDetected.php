<?php

namespace App\Notifications\Teams;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AnomalousUsageDetected extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $teamName,
        public string $apiKeyName,
        public int $requestCount,
        public int $errorCount,
        public float $errorRate,
        public int $windowMinutes,
    ) {
        //
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('Anomalous usage detected for :team', ['team' => $this->teamName]))
            ->line(__('API key ":key" generated :count requests in the last :minutes minutes, with an error rate of :pct%.', [
                'key' => $this->apiKeyName,
                'count' => $this->requestCount,
                'minutes' => $this->windowMinutes,
                'pct' => round($this->errorRate),
            ]))
            ->line(__('Errors: :errors / :total', [
                'errors' => $this->errorCount,
                'total' => $this->requestCount,
            ]))
            ->line(__('This may indicate a misconfigured integration or a compromised key. Review your usage and rotate the key if needed.'))
            ->action(__('View dashboard'), url('/dashboard'));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'team_name' => $this->teamName,
            'api_key_name' => $this->apiKeyName,
            'request_count' => $this->requestCount,
            'error_count' => $this->errorCount,
            'error_rate' => $this->errorRate,
            'window_minutes' => $this->windowMinutes,
        ];
    }
}
