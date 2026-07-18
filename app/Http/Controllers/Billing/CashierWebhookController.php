<?php

namespace App\Http\Controllers\Billing;

use App\Actions\Billing\SyncQuotaFromSubscription;
use App\Models\User;
use App\Services\PlanService;
use Illuminate\Http\Request;
use Laravel\Cashier\Events\WebhookHandled;
use Laravel\Cashier\Http\Controllers\WebhookController as CashierBaseWebhookController;
use Symfony\Component\HttpFoundation\Response;

class CashierWebhookController extends CashierBaseWebhookController
{
    public function __construct(
        private readonly SyncQuotaFromSubscription $syncQuotaFromSubscription,
        private readonly PlanService $planService,
    ) {
        parent::__construct();
    }

    /**
     * Handle a Stripe webhook call.
     *
     * Extends Cashier's dispatcher so that subscription lifecycle events also
     * sync the user's quota policy (plan tier + token limits) after Cashier
     * updates its own subscription record.
     */
    public function handleWebhook(Request $request): Response
    {
        $payload = json_decode($request->getContent(), true);
        $type = $payload['type'] ?? '';

        return match ($type) {
            'customer.subscription.created',
            'customer.subscription.updated',
            'customer.subscription.deleted' => $this->handleSubscriptionChange($payload),
            default => parent::handleWebhook($request),
        };
    }

    /**
     * Handle subscription lifecycle events — sync quota policy from Cashier subscription.
     *
     * @param  array<string, mixed>  $payload
     */
    protected function handleSubscriptionChange(array $payload): Response
    {
        // Let Cashier's parent handle the subscription record first.
        $encodedPayload = json_encode($payload);
        $response = parent::handleWebhook(
            new Request([], [], [], [], [], [], is_string($encodedPayload) ? $encodedPayload : ''),
        );

        $dataObject = data_get($payload, 'data.object');

        if (is_array($dataObject)) {
            $this->syncQuotaFromStripeSubscription($dataObject);
        }

        WebhookHandled::dispatch($payload);

        return $response;
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
        $user = $this->resolveUserFromPayload($dataObject);

        if (! $user) {
            return;
        }

        $stripeStatus = (string) data_get($dataObject, 'status', 'inactive');

        // Resolve plan code from the Stripe price ID in the subscription items.
        $stripePriceId = (string) data_get(
            $dataObject,
            'items.data.0.price.id',
            '',
        );
        $planCode = $this->planService->resolveCodeFromPriceId($stripePriceId);

        $this->syncQuotaFromSubscription->handle(
            user: $user,
            planCode: $planCode,
            status: $stripeStatus,
        );
    }

    // ------------------------------------------------------------------
    //  Helpers
    // ------------------------------------------------------------------

    /**
     * @param  array<string, mixed>  $dataObject
     */
    protected function resolveUserFromPayload(array $dataObject): ?User
    {
        $userId = (int) data_get($dataObject, 'metadata.user_id', 0);

        if ($userId > 0) {
            return $this->resolveUserById($userId);
        }

        // Fallback: resolve via the Stripe customer ID on the user record.
        $customerId = (string) data_get($dataObject, 'customer', '');

        if ($customerId === '') {
            return null;
        }

        return User::query()->where('stripe_id', $customerId)->first();
    }

    protected function resolveUserById(int $userId): ?User
    {
        if ($userId <= 0) {
            return null;
        }

        return User::query()->find($userId);
    }
}
