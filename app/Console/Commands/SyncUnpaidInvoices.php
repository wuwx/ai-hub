<?php

namespace App\Console\Commands;

use App\Actions\Billing\CheckStripeCheckoutPayment;
use App\Models\BillingInvoice;
use App\Models\Team;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('billing:sync-unpaid-invoices')]
#[Description('Poll Stripe for payment status of unpaid invoices and update local records.')]
class SyncUnpaidInvoices extends Command
{
    public function __construct(
        private readonly CheckStripeCheckoutPayment $checkStripeCheckoutPayment,
    ) {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $invoices = BillingInvoice::query()
            ->whereIn('status', ['issued', 'overdue'])
            ->whereNotNull('payment_reference')
            ->where('payment_reference', 'like', 'cs_%')
            ->get();

        if ($invoices->isEmpty()) {
            $this->info('No unpaid invoices with Stripe checkout sessions found.');

            return self::SUCCESS;
        }

        $synced = 0;
        $errors = 0;

        foreach ($invoices as $invoice) {
            try {
                if ($this->syncInvoicePayment($invoice)) {
                    $synced++;
                }
            } catch (\Exception $e) {
                $errors++;
                $this->error(sprintf(
                    'Failed to sync invoice #%s: %s',
                    $invoice->invoice_number,
                    $e->getMessage()
                ));
            }
        }

        $this->info(sprintf(
            'Checked %d invoice(s): %d marked as paid, %d error(s).',
            $invoices->count(),
            $synced,
            $errors
        ));

        return $errors > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Sync payment status for a single invoice from Stripe.
     */
    protected function syncInvoicePayment(BillingInvoice $invoice): bool
    {
        $team = Team::query()->find($invoice->team_id);

        if (! $team) {
            $this->warn(sprintf('Team not found for invoice #%s.', $invoice->invoice_number));

            return false;
        }

        $paymentStatus = $this->checkStripeCheckoutPayment->handle($invoice->payment_reference);

        if ($paymentStatus !== 'paid') {
            return false;
        }

        $invoice->forceFill([
            'status' => 'paid',
            'paid_at' => now(),
        ])->save();

        $this->info(sprintf('Invoice #%s marked as paid.', $invoice->invoice_number));

        return true;
    }
}
