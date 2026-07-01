<?php

namespace App\Http\Controllers\Billing;

use App\Actions\Billing\RechargeTeamWallet;
use App\Actions\Billing\SyncTeamQuotaFromSubscription;
use App\Models\BillingInvoice;
use App\Models\Team;
use App\Models\TeamWalletTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Laravel\Cashier\Events\WebhookHandled;
use Laravel\Cashier\Http\Controllers\WebhookController as CashierBaseWebhookController;
use Laravel\Cashier\Subscription as CashierSubscription;
use Symfony\Component\HttpFoundation\Response;

class CashierWebhookController extends CashierBaseWebhookController
{
    public function __construct(
        private readonly SyncTeamQuotaFromSubscription $syncTeamQuotaFromSubscription,
        private readonly RechargeTeamWallet $rechargeTeamWallet,
    ) {
        parent::__construct();
    }

    /**
     * Handle a Stripe webhook call.
     *
     * Extends Cashier's dispatcher to handle wallet-recharge, invoice-payment,
     * and refund events that are specific to this application.
     */
    public function handleWebhook(Request $request): Response
    {
        $payload = json_decode($request->getContent(), true);
        $type = $payload['type'] ?? '';

        return match ($type) {
            'checkout.session.completed',
            'checkout.session.async_payment_succeeded',
            'invoice.paid' => $this->handleCheckoutOrInvoicePaid($payload),
            'charge.refunded',
            'charge.refund.updated' => $this->handleChargeRefunded($payload),
            'customer.subscription.created',
            'customer.subscription.updated',
            'customer.subscription.deleted' => $this->handleSubscriptionChange($payload),
            default => parent::handleWebhook($request),
        };
    }

    /**
     * Route checkout/invoice-paid events to wallet-recharge or invoice-payment handlers.
     *
     * @param  array<string, mixed>  $payload
     */
    protected function handleCheckoutOrInvoicePaid(array $payload): Response
    {
        $dataObject = data_get($payload, 'data.object');

        if (! is_array($dataObject)) {
            return $this->successMethod();
        }

        if ($this->isWalletRechargeEvent($dataObject)) {
            $this->applyWalletRecharge($dataObject);
        } else {
            $this->markInvoicePaid($dataObject);
        }

        WebhookHandled::dispatch($payload);

        return $this->successMethod();
    }

    /**
     * Handle subscription lifecycle events — sync quota policy from Cashier subscription.
     *
     * @param  array<string, mixed>  $payload
     */
    protected function handleSubscriptionChange(array $payload): Response
    {
        // Let Cashier's parent handle the subscription record first.
        $response = parent::handleWebhook(
            new Request([], [], [], [], [], [], json_encode($payload)),
        );

        $dataObject = data_get($payload, 'data.object');

        if (is_array($dataObject)) {
            $this->syncQuotaFromStripeSubscription($dataObject);
        }

        WebhookHandled::dispatch($payload);

        return $response;
    }

    /**
     * Record Stripe refund events against the local invoice.
     *
     * @param  array<string, mixed>  $payload
     */
    protected function handleChargeRefunded(array $payload): Response
    {
        $dataObject = data_get($payload, 'data.object');

        if (is_array($dataObject)) {
            $this->markInvoiceRefunded($dataObject, (string) ($payload['type'] ?? ''));
        }

        WebhookHandled::dispatch($payload);

        return $this->successMethod();
    }

    // ------------------------------------------------------------------
    //  Subscription quota sync
    // ------------------------------------------------------------------

    /**
     * Sync quota policy from a Stripe subscription event payload.
     *
     * @param  array<string, mixed>  $dataObject
     */
    protected function syncQuotaFromStripeSubscription(array $dataObject): void
    {
        $team = $this->resolveTeamFromPayload($dataObject);

        if (! $team) {
            return;
        }

        $stripeSubscriptionId = $this->nullableString(data_get($dataObject, 'id'));
        $stripeStatus = (string) data_get($dataObject, 'status', 'inactive');

        // Resolve plan code from the Stripe price ID in the subscription items.
        $stripePriceId = (string) data_get($dataObject, 'items.data.0.price.id', '');
        $planCode = $this->resolvePlanCodeFromPriceId($stripePriceId);

        $this->syncTeamQuotaFromSubscription->handle(
            team: $team,
            planCode: $planCode,
            status: $stripeStatus,
        );
    }

    /**
     * Resolve our internal plan code from a Stripe price ID.
     */
    protected function resolvePlanCodeFromPriceId(string $stripePriceId): string
    {
        if ($stripePriceId === '') {
            return (string) config('services.billing.free_plan_code', 'free');
        }

        $plans = (array) config('services.billing.plans', []);

        foreach ($plans as $code => $plan) {
            if (($plan['stripe_price_id'] ?? null) === $stripePriceId) {
                return (string) $code;
            }
        }

        return (string) config('services.billing.free_plan_code', 'free');
    }

    // ------------------------------------------------------------------
    //  Wallet recharge
    // ------------------------------------------------------------------

    /**
     * @param  array<string, mixed>  $dataObject
     */
    protected function isWalletRechargeEvent(array $dataObject): bool
    {
        $teamId = (int) data_get($dataObject, 'metadata.wallet_recharge_team_id', 0);

        return $teamId > 0;
    }

    /**
     * @param  array<string, mixed>  $dataObject
     */
    protected function applyWalletRecharge(array $dataObject): void
    {
        $teamId = (int) data_get($dataObject, 'metadata.wallet_recharge_team_id', 0);
        $team = $this->resolveTeamById($teamId);

        if (! $team) {
            Log::warning('Stripe wallet recharge webhook: team not found', ['team_id' => $teamId]);

            return;
        }

        $referenceId = (string) (data_get($dataObject, 'id')
            ?? data_get($dataObject, 'payment_intent')
            ?? '');

        if ($referenceId === '') {
            Log::warning('Stripe wallet recharge webhook: missing reference id', ['team_id' => $teamId]);

            return;
        }

        $existing = TeamWalletTransaction::query()
            ->where('reference_id', $referenceId)
            ->where('type', 'recharge')
            ->exists();

        if ($existing) {
            return;
        }

        $amountCents = (int) data_get($dataObject, 'amount_total', data_get($dataObject, 'amount', 0));

        if ($amountCents <= 0) {
            Log::warning('Stripe wallet recharge webhook: non-positive amount', [
                'team_id' => $teamId,
                'amount_cents' => $amountCents,
            ]);

            return;
        }

        $this->rechargeTeamWallet->handle(
            team: $team,
            amountCents: $amountCents,
            description: sprintf('Stripe recharge — session %s', $referenceId),
            referenceId: $referenceId,
            metadata: [
                'stripe_event' => 'checkout.session.completed',
                'stripe_session_id' => $referenceId,
                'currency' => (string) data_get($dataObject, 'currency', 'usd'),
            ],
        );
    }

    // ------------------------------------------------------------------
    //  Invoice Payment
    // ------------------------------------------------------------------

    /**
     * @param  array<string, mixed>  $dataObject
     */
    protected function markInvoicePaid(array $dataObject): void
    {
        $invoiceNumber = (string) data_get($dataObject, 'metadata.invoice_number', '');

        if ($invoiceNumber === '') {
            return;
        }

        $invoice = BillingInvoice::query()
            ->where('invoice_number', $invoiceNumber)
            ->first();

        if (! $invoice || $invoice->isFinalized()) {
            return;
        }

        $paymentReference = data_get($dataObject, 'payment_intent');

        if (! is_string($paymentReference) || $paymentReference === '') {
            $paymentReference = (string) ($dataObject['id'] ?? $invoice->payment_reference);
        }

        $invoice->forceFill([
            'status' => 'paid',
            'paid_at' => now(),
            'payment_reference' => $paymentReference,
        ])->save();
    }

    // ------------------------------------------------------------------
    //  Refunds
    // ------------------------------------------------------------------

    /**
     * @param  array<string, mixed>  $dataObject
     */
    protected function markInvoiceRefunded(array $dataObject, string $eventType): void
    {
        $invoiceNumber = (string) data_get($dataObject, 'metadata.invoice_number', '');
        $paymentIntent = (string) data_get($dataObject, 'payment_intent', '');

        $invoice = BillingInvoice::query()
            ->when($invoiceNumber !== '', fn ($query) => $query->where('invoice_number', $invoiceNumber))
            ->when($invoiceNumber === '' && $paymentIntent !== '', fn ($query) => $query->where('payment_reference', $paymentIntent))
            ->first();

        if (! $invoice) {
            return;
        }

        $eventReference = (string) data_get($dataObject, 'id', 'stripe_refund');
        $existingNotes = trim((string) $invoice->notes);

        $refundMessage = sprintf('Refund event %s recorded at %s via Stripe.', $eventReference, now()->toDateTimeString());

        if ($existingNotes !== '' && str_contains($existingNotes, $eventReference)) {
            return;
        }

        $refundAmount = $this->nullableInt(data_get($dataObject, 'amount_refunded'));
        $chargeAmount = $this->nullableInt(data_get($dataObject, 'amount'));

        $isFullRefund = $eventType === 'charge.refunded';

        if ($refundAmount !== null && $chargeAmount !== null) {
            $isFullRefund = $refundAmount >= $chargeAmount;
        }

        $invoice->forceFill([
            'status' => $isFullRefund ? 'void' : $invoice->status,
            'notes' => $existingNotes !== '' ? $existingNotes.PHP_EOL.$refundMessage : $refundMessage,
        ])->save();
    }

    // ------------------------------------------------------------------
    //  Helpers
    // ------------------------------------------------------------------

    /**
     * @param  array<string, mixed>  $dataObject
     */
    protected function resolveTeamFromPayload(array $dataObject): ?Team
    {
        $teamId = (int) data_get($dataObject, 'metadata.team_id', 0);

        if ($teamId > 0) {
            return $this->resolveTeamById($teamId);
        }

        // Fallback: resolve via Cashier subscription record.
        $customerId = (string) data_get($dataObject, 'customer', '');

        if ($customerId === '') {
            return null;
        }

        $cashierSubscription = CashierSubscription::query()
            ->whereHas('owner', fn ($q) => $q->where('stripe_id', $customerId))
            ->first();

        if ($cashierSubscription && $cashierSubscription->owner instanceof Team) {
            return $cashierSubscription->owner;
        }

        return null;
    }

    protected function resolveTeamById(int $teamId): ?Team
    {
        if ($teamId <= 0) {
            return null;
        }

        return Team::query()->find($teamId);
    }

    protected function nullableString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed !== '' ? $trimmed : null;
    }

    protected function nullableInt(mixed $value): ?int
    {
        if (! is_numeric($value)) {
            return null;
        }

        return (int) $value;
    }
}
