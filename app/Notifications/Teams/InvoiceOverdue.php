<?php

namespace App\Notifications\Teams;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;

class InvoiceOverdue extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $teamName,
        public string $invoiceNumber,
        public int $totalCents,
        public string $currency,
        public ?string $dueAt,
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
        $totalFormatted = number_format($this->totalCents / 100, 2).' '.$this->currency;
        $dueDate = $this->dueAt ? Carbon::parse($this->dueAt)->toFormattedDateString() : '—';

        return (new MailMessage)
            ->subject(__('Invoice :number is overdue for :team', [
                'number' => $this->invoiceNumber,
                'team' => $this->teamName,
            ]))
            ->line(__('Invoice :number for :team is now overdue.', [
                'number' => $this->invoiceNumber,
                'team' => $this->teamName,
            ]))
            ->line(__('Amount due: :amount', ['amount' => $totalFormatted]))
            ->line(__('Was due on: :date', ['date' => $dueDate]))
            ->line(__('Please pay promptly to avoid service interruption.'))
            ->action(__('View billing'), url('/billing'));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'team_name' => $this->teamName,
            'invoice_number' => $this->invoiceNumber,
            'total_cents' => $this->totalCents,
            'currency' => $this->currency,
            'due_at' => $this->dueAt,
        ];
    }
}
