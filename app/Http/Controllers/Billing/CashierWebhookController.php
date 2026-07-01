<?php

namespace App\Http\Controllers\Billing;

use App\Actions\Billing\RechargeTeamWallet;
use App\Actions\Billing\SyncTeamQuotaFromSubscription;
use App\Models\BillingInvoice;
use App\Models\Team;
use App\Models\TeamBillingSubscription;
use App\Models\TeamWalletTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Laravel\Cashier\Events\WebhookHandled;
use Laravel\Cashier\Http\Controllers\WebhookController as CashierBaseWebhookController;
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
    //  Subscription events — delegate to Cashier, then sync local quota
    // ------------------------------------------------------------------

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function handleCustomerSubscriptionCreated(array $payload): Response
    {
        $response = parent::handleCustomerSubscriptionCreated($payload);

        $this->syncLocalSubscription($payload);

        return $response;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function handleCustomerSubscriptionUpdated(array $payload): Response
    {
        $response = parent::handleCustomerSubscriptionUpdated($payload);

        $this->syncLocalSubscription($payload);

        return $response;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function handleCustomerSubscriptionDeleted(array $payload): Response
    {
        $response = parent::handleCustomerSubscriptionDeleted($payload);

        $this->handleLocalSubscriptionDeleted($payload);

        return $response;
    }

    // ------------------------------------------------------------------
    //  Local subscription projection (plan code + quota sync)
    // ------------------------------------------------------------------

    /**
     * Sync the local TeamBillingSubscription from a Stripe subscription event.
     *
     * @param  array<string, mixed>  $payload
     */
    protected function syncLocalSubscription(array $payload): void
    {
        $dataObject = data_get($payload, 'data.object');

        if (! is_array($dataObject)) {
            return;
        }

        $team = $this->resolveTeamFromPayload($dataObject);

        if (! $team) {
            return;
        }

        $planCode = $this->resolvePlanCodeFromPayload($dataObject);

        $subscription = TeamBillingSubscription::query()->updateOrCreate(
            ['team_id' => $team->id],
            [
                'payment_provider' => 'stripe',
                'stripe_customer_id' => $this->nullableString(data_get($dataObject, 'customer')),
                'stripe_subscription_id' => $this->nullableString(data_get($dataObject, 'id')),
                'plan_code' => $planCode,
                'status' => (string) data_get($dataObject, 'status', 'inactive'),
                'cancel_at_period_end' => (bool) data_get($dataObject, 'cancel_at_period_end', false),
                'current_period_start' => $this->toDateTime(data_get($dataObject, 'current_period_start')),
                'current_period_end' => $this->toDateTime(data_get($dataObject, 'current_period_end')),
                'meta' => ['event_source' => 'cashier_webhook'],
            ],
        );

        $this->syncTeamQuotaFromSubscription->handle($subscription);
    }

    /**
     * Handle local cleanup when a subscription is deleted in Stripe.
     *
     * @param  array<string, mixed>  $payload
     */
    protected function handleLocalSubscriptionDeleted(array $payload): void
    {
        $dataObject = data_get($payload, 'data.object');

        if (! is_array($dataObject)) {
            return;
        }

        $team = $this->resolveTeamFromPayload($dataObject);

        if (! $team) {
            return;
        }

        $subscription = TeamBillingSubscription::query()->where('team_id', $team->id)->first();

        if ($subscription) {
            $subscription->update([
                'status' => 'canceled',
                'cancel_at_period_end' => true,
            ]);

            $this->syncTeamQuotaFromSubscription->handle($subscription);
        }
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
    //  Invoice payment
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
            'payment_provider' => 'stripe',
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

        $customerId = (string) data_get($dataObject, 'customer', '');

        if ($customerId === '') {
            return null;
        }

        $existingSubscription = TeamBillingSubscription::query()
            ->where('stripe_customer_id', $customerId)
            ->first();

        return $existingSubscription?->team;
    }

    protected function resolveTeamById(int $teamId): ?Team
    {
        if ($teamId <= 0) {
            return null;
        }

        return Team::query()->find($teamId);
    }

    /**
     * Resolve the plan code from a Stripe subscription payload.
     *
     * Priority: metadata.plan_code → price lookup via config mapping → free plan.
     *
     * @param  array<string, mixed>  $dataObject
     */
    protected function resolvePlanCodeFromPayload(array $dataObject): string
    {
        $metadataPlanCode = (string) data_get($dataObject, 'metadata.plan_code', '');

        if ($metadataPlanCode !== '') {
            return $metadataPlanCode;
        }

        $stripePrice = (string) data_get($dataObject, 'items.data.0.price.id', '');

        if ($stripePrice !== '') {
            $plans = (array) config('services.billing.plans', []);

            foreach ($plans as $code => $plan) {
                if (($plan['stripe_price_id'] ?? null) === $stripePrice) {
                    return (string) $code;
                }
            }
        }

        return (string) config('services.billing.free_plan_code', 'free');
    }

    protected function toDateTime(mixed $value): ?string
    {
        if (! is_numeric($value)) {
            return null;
        }

        return now()->setTimestamp((int) $value)->toDateTimeString();
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
