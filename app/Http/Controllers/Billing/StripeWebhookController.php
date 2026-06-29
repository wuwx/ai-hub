<?php

namespace App\Http\Controllers\Billing;

use App\Actions\Billing\SyncTeamQuotaFromSubscription;
use App\Http\Controllers\Controller;
use App\Models\BillingInvoice;
use App\Models\Team;
use App\Models\TeamBillingSubscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StripeWebhookController extends Controller
{
    public function __construct(private readonly SyncTeamQuotaFromSubscription $syncTeamQuotaFromSubscription)
    {
        //
    }

    public function __invoke(Request $request): JsonResponse
    {
        $webhookSecret = (string) config('services.stripe.webhook_secret', '');
        $payload = (string) $request->getContent();
        $signatureHeader = (string) $request->header('Stripe-Signature', '');

        if ($webhookSecret === '' || ! $this->isValidSignature($payload, $signatureHeader, $webhookSecret)) {
            return response()->json(['error' => 'Invalid webhook signature.'], 400);
        }

        $event = json_decode($payload, true);

        if (! is_array($event)) {
            return response()->json(['error' => 'Invalid event payload.'], 400);
        }

        $eventType = (string) ($event['type'] ?? '');
        $dataObject = data_get($event, 'data.object');

        if (! is_array($dataObject)) {
            return response()->json(['received' => true]);
        }

        if (in_array($eventType, ['checkout.session.completed', 'checkout.session.async_payment_succeeded', 'invoice.paid'], true)) {
            $this->markInvoicePaid($dataObject);
        }

        if (in_array($eventType, ['customer.subscription.created', 'customer.subscription.updated', 'customer.subscription.deleted'], true)) {
            $this->syncSubscription($dataObject);
        }

        if (in_array($eventType, ['charge.refunded', 'charge.refund.updated'], true)) {
            $this->markInvoiceRefunded($dataObject, $eventType);
        }

        return response()->json(['received' => true]);
    }

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

    /**
     * @param  array<string, mixed>  $dataObject
     */
    protected function syncSubscription(array $dataObject): void
    {
        $team = $this->resolveTeamForSubscription($dataObject);

        if (! $team) {
            return;
        }

        $planCode = (string) data_get($dataObject, 'metadata.plan_code', data_get($dataObject, 'items.data.0.price.lookup_key', config('services.billing.free_plan_code', 'free')));

        $subscription = TeamBillingSubscription::query()->updateOrCreate(
            ['team_id' => $team->id],
            [
                'payment_provider' => 'stripe',
                'stripe_customer_id' => $this->nullableString(data_get($dataObject, 'customer')),
                'stripe_subscription_id' => $this->nullableString(data_get($dataObject, 'id')),
                'plan_code' => $planCode !== '' ? $planCode : (string) config('services.billing.free_plan_code', 'free'),
                'status' => (string) data_get($dataObject, 'status', 'inactive'),
                'cancel_at_period_end' => (bool) data_get($dataObject, 'cancel_at_period_end', false),
                'current_period_start' => $this->toDateTime(data_get($dataObject, 'current_period_start')),
                'current_period_end' => $this->toDateTime(data_get($dataObject, 'current_period_end')),
                'meta' => [
                    'event_source' => 'stripe_webhook',
                ],
            ],
        );

        $this->syncTeamQuotaFromSubscription->handle($subscription);
    }

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

    /**
     * @param  array<string, mixed>  $dataObject
     */
    protected function resolveTeamForSubscription(array $dataObject): ?Team
    {
        $teamId = (int) data_get($dataObject, 'metadata.team_id', 0);

        if ($teamId > 0) {
            return Team::query()->find($teamId);
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

    protected function isValidSignature(string $payload, string $signatureHeader, string $secret): bool
    {
        if ($payload === '' || $signatureHeader === '') {
            return false;
        }

        $parts = [];

        foreach (explode(',', $signatureHeader) as $segment) {
            [$key, $value] = array_pad(explode('=', trim($segment), 2), 2, null);

            if (is_string($key) && is_string($value)) {
                $parts[$key][] = $value;
            }
        }

        $timestamp = isset($parts['t'][0]) ? (int) $parts['t'][0] : 0;
        $signatures = $parts['v1'] ?? [];

        if ($timestamp <= 0 || empty($signatures)) {
            return false;
        }

        $tolerance = max(1, (int) config('services.stripe.webhook_tolerance_seconds', 300));

        if (abs(now()->timestamp - $timestamp) > $tolerance) {
            return false;
        }

        $expected = hash_hmac('sha256', $timestamp.'.'.$payload, $secret);

        foreach ($signatures as $signature) {
            if (hash_equals($expected, (string) $signature)) {
                return true;
            }
        }

        return false;
    }
}
