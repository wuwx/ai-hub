<?php

namespace App\Actions\Billing;

use App\Models\BillingInvoice;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class CreateStripeCheckoutSession
{
    public function handle(BillingInvoice $invoice): BillingInvoice
    {
        if ($invoice->isFinalized()) {
            throw new RuntimeException('Cannot create a checkout session for a finalized invoice.');
        }

        $stripeSecret = (string) config('services.stripe.secret', '');

        if ($stripeSecret === '') {
            throw new RuntimeException('Stripe secret key is not configured.');
        }

        $successUrl = (string) config('services.billing.checkout_success_url', rtrim((string) config('app.url'), '/').'/billing/success');
        $cancelUrl = (string) config('services.billing.checkout_cancel_url', rtrim((string) config('app.url'), '/').'/billing/cancel');

        try {
            $response = Http::asForm()
                ->withToken($stripeSecret)
                ->post('https://api.stripe.com/v1/checkout/sessions', $this->buildPayload($invoice, $successUrl, $cancelUrl));
        } catch (ConnectionException $exception) {
            throw new RuntimeException('Unable to reach Stripe API.', previous: $exception);
        }

        if ($response->failed()) {
            throw new RuntimeException('Stripe API rejected checkout session creation.');
        }

        $body = $response->json();

        if (! is_array($body) || ! is_string($body['id'] ?? null) || ! is_string($body['url'] ?? null)) {
            throw new RuntimeException('Stripe API returned an invalid checkout session payload.');
        }

        $invoice->forceFill([
            'payment_provider' => 'stripe',
            'payment_reference' => $body['id'],
            'payment_url' => $body['url'],
            'status' => $invoice->status === 'draft' ? 'issued' : $invoice->status,
        ])->save();

        return $invoice;
    }

    /**
     * @return array<string, int|string>
     */
    protected function buildPayload(BillingInvoice $invoice, string $successUrl, string $cancelUrl): array
    {
        return [
            'mode' => 'payment',
            'success_url' => $successUrl,
            'cancel_url' => $cancelUrl,
            'metadata[invoice_number]' => $invoice->invoice_number,
            'metadata[team_id]' => (string) $invoice->team_id,
            'line_items[0][quantity]' => 1,
            'line_items[0][price_data][currency]' => strtolower($invoice->currency),
            'line_items[0][price_data][unit_amount]' => $invoice->total_cents,
            'line_items[0][price_data][product_data][name]' => sprintf('AI usage invoice %s', $invoice->invoice_number),
        ];
    }
}
