<?php

namespace App\Actions\Billing;

use App\Models\Team;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class CreateWalletRechargeSession
{
    /**
     * Create a Stripe Checkout session for topping up a team wallet.
     *
     * @return array{session_id: string, url: string}
     *
     * @throws RuntimeException When Stripe is not configured or the API rejects the request.
     */
    public function handle(Team $team, int $amountCents, string $currency = 'USD'): array
    {
        if ($amountCents <= 0) {
            throw new RuntimeException('Recharge amount must be positive.');
        }

        $stripeSecret = (string) config('services.stripe.secret', '');

        if ($stripeSecret === '') {
            throw new RuntimeException('Stripe secret key is not configured.');
        }

        $successUrl = (string) config('services.billing.wallet_recharge_success_url', rtrim((string) config('app.url'), '/').'/billing/wallet/success');
        $cancelUrl = (string) config('services.billing.wallet_recharge_cancel_url', rtrim((string) config('app.url'), '/').'/billing/wallet/cancel');

        try {
            $response = Http::asForm()
                ->withToken($stripeSecret)
                ->post('https://api.stripe.com/v1/checkout/sessions', $this->buildPayload($team, $amountCents, $currency, $successUrl, $cancelUrl));
        } catch (ConnectionException $exception) {
            throw new RuntimeException('Unable to reach Stripe API.', previous: $exception);
        }

        if ($response->failed()) {
            throw new RuntimeException('Stripe API rejected wallet recharge session creation.');
        }

        $body = $response->json();

        if (! is_array($body) || ! is_string($body['id'] ?? null) || ! is_string($body['url'] ?? null)) {
            throw new RuntimeException('Stripe API returned an invalid checkout session payload.');
        }

        return [
            'session_id' => $body['id'],
            'url' => $body['url'],
        ];
    }

    /**
     * @return array<string, int|string>
     */
    protected function buildPayload(Team $team, int $amountCents, string $currency, string $successUrl, string $cancelUrl): array
    {
        return [
            'mode' => 'payment',
            'success_url' => $successUrl,
            'cancel_url' => $cancelUrl,
            // Metadata that the webhook controller uses to route this payment
            // to the wallet-recharge code path (instead of invoice payment).
            'metadata[wallet_recharge_team_id]' => (string) $team->id,
            'metadata[recharge_amount_cents]' => (string) $amountCents,
            'metadata[recharge_currency]' => strtolower($currency),
            'line_items[0][quantity]' => 1,
            'line_items[0][price_data][currency]' => strtolower($currency),
            'line_items[0][price_data][unit_amount]' => $amountCents,
            'line_items[0][price_data][product_data][name]' => sprintf('Wallet top-up — team %d', $team->id),
        ];
    }
}
