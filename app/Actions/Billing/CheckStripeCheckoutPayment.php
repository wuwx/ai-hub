<?php

namespace App\Actions\Billing;

use Laravel\Cashier\Cashier;

class CheckStripeCheckoutPayment
{
    /**
     * Check if a Stripe checkout session has been paid.
     *
     * @return string The payment_status value from Stripe (e.g. 'paid', 'unpaid', 'no_payment_required').
     */
    public function handle(string $checkoutSessionId): string
    {
        $stripe = Cashier::stripe();
        $session = $stripe->checkout->sessions->retrieve($checkoutSessionId);

        return (string) $session->payment_status;
    }
}
