<?php

namespace App\Console\Commands;

use App\Actions\Webhooks\DispatchWebhookEvent;
use App\Models\BillingInvoice;
use App\Notifications\Teams\InvoiceOverdue;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('billing:mark-overdue-invoices')]
#[Description('Mark issued invoices as overdue when due date has passed and notify team owners.')]
class MarkOverdueInvoices extends Command
{
    public function __construct(
        private readonly DispatchWebhookEvent $dispatchWebhookEvent,
    ) {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $invoices = BillingInvoice::query()
            ->where('status', 'issued')
            ->whereNotNull('due_at')
            ->where('due_at', '<', now())
            ->get();

        if ($invoices->isEmpty()) {
            $this->info('Marked 0 invoice(s) as overdue.');

            return self::SUCCESS;
        }

        $notified = 0;

        foreach ($invoices as $invoice) {
            $invoice->update(['status' => 'overdue']);

            $team = $invoice->team;
            $owner = $team?->owner();

            if ($owner) {
                $owner->notify(new InvoiceOverdue(
                    teamName: $team->name,
                    invoiceNumber: $invoice->invoice_number,
                    totalCents: $invoice->total_cents,
                    currency: $invoice->currency,
                    dueAt: $invoice->due_at?->toIso8601String(),
                ));

                $notified++;
            }

            $this->dispatchWebhookEvent->handle($team, 'invoice.overdue', [
                'invoice_number' => $invoice->invoice_number,
                'total_cents' => $invoice->total_cents,
                'currency' => $invoice->currency,
                'due_at' => $invoice->due_at?->toIso8601String(),
            ]);
        }

        $this->info(sprintf('Marked %d invoice(s) as overdue, notified %d owner(s).', $invoices->count(), $notified));

        return self::SUCCESS;
    }
}
