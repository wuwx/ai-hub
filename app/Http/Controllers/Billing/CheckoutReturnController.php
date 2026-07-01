<?php

namespace App\Http\Controllers\Billing;

use App\Actions\Billing\RechargeTeamWallet;
use App\Models\BillingInvoice;
use App\Models\Team;
use App\Models\TeamWalletTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Handle the user redirect back from Stripe Checkout.
 *
 * Stripe appends ?session_id=cs_xxx to the success URL. We use this to
 * retrieve the session from Stripe and confirm the payment locally — this
 * acts as a safety net when the webhook has not yet arrived (common in
 * local development or when the webhook endpoint is unreachable).
 */
class CheckoutReturnController
{
    public function __construct(
        private readonly RechargeTeamWallet $rechargeTeamWallet,
    ) {}

    /**
     * Confirm an invoice payment checkout session.
     */
    public function invoice(Request $request, Team $current_team)
    {
        $sessionId = $request->query('session_id');

        if ($sessionId) {
            $this->confirmInvoiceSession($current_team, $sessionId);
        }

        return to_route('billing.index');
    }

    /**
     * Confirm a wallet recharge checkout session.
     */
    public function wallet(Request $request, Team $current_team)
    {
        $sessionId = $request->query('session_id');

        if ($sessionId) {
            $this->confirmWalletSession($current_team, $sessionId);
        }

        return to_route('billing.index');
    }

    /**
     * Confirm an invoice payment from a Stripe checkout session.
     */
    protected function confirmInvoiceSession(Team $team, string $sessionId): void
    {
        try {
            $session = $team->stripe()->checkout->sessions->retrieve($sessionId, []);
        } catch (\Exception $e) {
            Log::warning('Failed to retrieve Stripe checkout session on return', ['session_id' => $sessionId, 'error' => $e->getMessage()]);

            return;
        }

        if (($session->payment_status ?? '') !== 'paid') {
            return;
        }

        $invoiceNumber = $session->metadata['invoice_number'] ?? '';

        if ($invoiceNumber === '') {
            return;
        }

        $invoice = BillingInvoice::query()
            ->where('invoice_number', $invoiceNumber)
            ->first();

        if (! $invoice || $invoice->isFinalized()) {
            return;
        }

        $paymentReference = is_string($session->payment_intent ?? null)
            ? $session->payment_intent
            : $sessionId;

        $invoice->forceFill([
            'status' => 'paid',
            'paid_at' => now(),
            'payment_reference' => $paymentReference,
        ])->save();
    }

    /**
     * Confirm a wallet recharge from a Stripe checkout session.
     */
    protected function confirmWalletSession(Team $team, string $sessionId): void
    {
        try {
            $session = $team->stripe()->checkout->sessions->retrieve($sessionId, []);
        } catch (\Exception $e) {
            Log::warning('Failed to retrieve Stripe checkout session on return', ['session_id' => $sessionId, 'error' => $e->getMessage()]);

            return;
        }

        if (($session->payment_status ?? '') !== 'paid') {
            return;
        }

        $teamId = (int) ($session->metadata['wallet_recharge_team_id'] ?? 0);

        if ($teamId !== $team->id) {
            return;
        }

        $referenceId = $sessionId;

        $existing = TeamWalletTransaction::query()
            ->where('reference_id', $referenceId)
            ->where('type', 'recharge')
            ->exists();

        if ($existing) {
            return;
        }

        $amountCents = (int) ($session->amount_total ?? 0);

        if ($amountCents <= 0) {
            return;
        }

        $this->rechargeTeamWallet->handle(
            team: $team,
            amountCents: $amountCents,
            description: sprintf('Stripe recharge — session %s', $referenceId),
            referenceId: $referenceId,
            metadata: [
                'stripe_event' => 'checkout_return',
                'stripe_session_id' => $referenceId,
                'currency' => (string) ($session->currency ?? 'usd'),
            ],
        );
    }
}
