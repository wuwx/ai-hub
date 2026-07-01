<?php

namespace App\Actions\Billing;

use App\Models\BillingInvoice;
use App\Models\Team;
use RuntimeException;

class CreateStripeCheckoutSession
{
    public function handle(BillingInvoice $invoice): BillingInvoice
    {
        if ($invoice->isFinalized()) {
            throw new RuntimeException('Cannot create a checkout session for a finalized invoice.');
        }

        $team = Team::query()->find($invoice->team_id);

        if (! $team) {
            throw new RuntimeException('Team not found for invoice.');
        }

        $successUrl = (string) config('services.billing.checkout_success_url', rtrim((string) config('app.url'), '/').'/billing/success');
        $cancelUrl = (string) config('services.billing.checkout_cancel_url', rtrim((string) config('app.url'), '/').'/billing/cancel');

        try {
            $checkout = $team->checkoutCharge(
                amount: $invoice->total_cents,
                name: sprintf('AI usage invoice %s', $invoice->invoice_number),
                sessionOptions: [
                    'success_url' => $successUrl,
                    'cancel_url' => $cancelUrl,
                    'metadata' => [
                        'invoice_number' => $invoice->invoice_number,
                        'team_id' => (string) $invoice->team_id,
                    ],
                ],
            );
        } catch (\Exception $exception) {
            throw new RuntimeException('Unable to create checkout session via Cashier.', previous: $exception);
        }

        $invoice->forceFill([
            'payment_provider' => 'stripe',
            'payment_reference' => $checkout->id,
            'payment_url' => $checkout->url,
            'status' => $invoice->status === 'draft' ? 'issued' : $invoice->status,
        ])->save();

        return $invoice;
    }
}
