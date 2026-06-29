<?php

namespace App\Console\Commands;

use App\Models\BillingInvoice;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('billing:mark-overdue-invoices')]
#[Description('Mark issued invoices as overdue when due date has passed.')]
class MarkOverdueInvoices extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $updated = BillingInvoice::query()
            ->where('status', 'issued')
            ->whereNotNull('due_at')
            ->where('due_at', '<', now())
            ->update(['status' => 'overdue']);

        $this->info(sprintf('Marked %d invoice(s) as overdue.', $updated));

        return self::SUCCESS;
    }
}
