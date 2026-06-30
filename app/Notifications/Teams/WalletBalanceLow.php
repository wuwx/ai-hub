<?php

namespace App\Notifications\Teams;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class WalletBalanceLow extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $teamName,
        public int $balanceCents,
        public string $currency,
        public int $thresholdCents,
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
        $balanceFormatted = number_format($this->balanceCents / 100, 2).' '.$this->currency;
        $thresholdFormatted = number_format($this->thresholdCents / 100, 2).' '.$this->currency;

        return (new MailMessage)
            ->subject(__('Low wallet balance for :team', ['team' => $this->teamName]))
            ->line(__('The wallet balance for :team has dropped below :threshold.', [
                'team' => $this->teamName,
                'threshold' => $thresholdFormatted,
            ]))
            ->line(__('Current balance: :balance', ['balance' => $balanceFormatted]))
            ->line(__('Requests will be rejected with HTTP 402 once the balance reaches zero.'))
            ->action(__('Recharge wallet'), url('/billing'));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'team_name' => $this->teamName,
            'balance_cents' => $this->balanceCents,
            'currency' => $this->currency,
            'threshold_cents' => $this->thresholdCents,
        ];
    }
}
