<?php

namespace App\Actions\Billing;

use App\Models\Team;
use RuntimeException;

class CreateWalletRechargeSession
{
    /**
     * Create a Stripe Checkout session for topping up a team wallet.
     *
     * @return array{session_id: string, url: string}
     *
     * @throws RuntimeException When the recharge amount is invalid or Cashier fails.
     */
    public function handle(Team $team, int $amountCents, string $currency = 'USD'): array
    {
        if ($amountCents <= 0) {
            throw new RuntimeException('Recharge amount must be positive.');
        }

        if (! config('cashier.secret')) {
            throw new RuntimeException('Stripe secret key is not configured.');
        }

        $successUrl = (string) config('services.billing.wallet_recharge_success_url', rtrim((string) config('app.url'), '/').'/billing/wallet/success');
        $cancelUrl = (string) config('services.billing.wallet_recharge_cancel_url', rtrim((string) config('app.url'), '/').'/billing/wallet/cancel');

        try {
            $checkout = $team->checkoutCharge(
                amount: $amountCents,
                name: sprintf('Wallet top-up — team %d', $team->id),
                sessionOptions: [
                    'success_url' => $successUrl,
                    'cancel_url' => $cancelUrl,
                    'metadata' => [
                        'wallet_recharge_team_id' => (string) $team->id,
                        'recharge_amount_cents' => (string) $amountCents,
                        'recharge_currency' => strtolower($currency),
                    ],
                ],
            );
        } catch (\Exception $exception) {
            throw new RuntimeException('Unable to create wallet recharge session via Cashier.', previous: $exception);
        }

        return [
            'session_id' => $checkout->id,
            'url' => $checkout->url,
        ];
    }
}
