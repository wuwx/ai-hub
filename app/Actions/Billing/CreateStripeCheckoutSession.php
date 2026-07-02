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

        $baseUrl = rtrim((string) config('app.url'), '/');
        $successUrl = (string) config('services.billing.checkout_success_url', $baseUrl.'/'.$team->slug.'/billing/success');
        $cancelUrl = (string) config('services.billing.checkout_cancel_url', $baseUrl.'/'.$team->slug.'/billing/cancel');

        $metadata = [
            'invoice_number' => $invoice->invoice_number,
            'team_id' => (string) $invoice->team_id,
        ];

        // If the invoice has a plan_code in notes, include it in metadata
        // so the webhook/return handler can activate the quota policy.
        if ($invoice->notes) {
            $notes = json_decode($invoice->notes, true);
            if (is_array($notes) && isset($notes['plan_code'])) {
                $metadata['plan_code'] = $notes['plan_code'];
            }
        }

        try {
            $checkout = $team->checkoutCharge(
                amount: $invoice->total_cents,
                name: sprintf('AI usage invoice %s', $invoice->invoice_number),
                sessionOptions: [
                    'success_url' => $successUrl,
                    'cancel_url' => $cancelUrl,
                    'metadata' => $metadata,
                ],
            );
        } catch (\Exception $exception) {
            throw new RuntimeException('Unable to create checkout session via Cashier.', previous: $exception);
        }

        $invoice->forceFill([
            'payment_reference' => $checkout->id,
            'payment_url' => $checkout->url,
            'status' => $invoice->status === 'draft' ? 'issued' : $invoice->status,
        ])->save();

        return $invoice;
    }
}
