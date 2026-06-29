<?php

namespace App\Notifications\Teams;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class QuotaThresholdExceeded extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public string $period,
        public int $used,
        public int $limit,
        public float $percentage,
        public string $teamName,
    ) {
        //
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('Quota threshold exceeded for :team', ['team' => $this->teamName]))
            ->line(__(':pct% of the :period token quota for :team has been consumed.', [
                'pct' => round($this->percentage),
                'period' => $this->period,
                'team' => $this->teamName,
            ]))
            ->line(__('Used: :used / :limit tokens', [
                'used' => number_format($this->used),
                'limit' => number_format($this->limit),
            ]))
            ->line(__('Requests will be rejected once the limit is reached. Review your usage or upgrade your plan.'))
            ->action(__('View dashboard'), url('/dashboard'));
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'period' => $this->period,
            'used' => $this->used,
            'limit' => $this->limit,
            'percentage' => $this->percentage,
            'team_name' => $this->teamName,
        ];
    }
}
