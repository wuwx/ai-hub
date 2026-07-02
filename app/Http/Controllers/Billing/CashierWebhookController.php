<?php

namespace App\Http\Controllers\Billing;

use App\Actions\Billing\SyncTeamQuotaFromSubscription;
use App\Models\Team;
use Illuminate\Http\Request;
use Laravel\Cashier\Events\WebhookHandled;
use Laravel\Cashier\Http\Controllers\WebhookController as CashierBaseWebhookController;
use Symfony\Component\HttpFoundation\Response;

class CashierWebhookController extends CashierBaseWebhookController
{
    public function __construct(
        private readonly SyncTeamQuotaFromSubscription $syncTeamQuotaFromSubscription,
    ) {
        parent::__construct();
    }

    /**
     * Handle a Stripe webhook call.
     *
     * Extends Cashier's dispatcher so that subscription lifecycle events also
     * sync the team's quota policy (plan tier + token limits) after Cashier
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

        $stripeStatus = (string) data_get($dataObject, 'status', 'inactive');

        // Resolve plan code from the Stripe price ID in the subscription items.
        $stripePriceId = (string) data_get(
            $dataObject,
            'items.data.0.price.id',
            '',
        );
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

        // Fallback: resolve via the Stripe customer ID on the team record.
        $customerId = (string) data_get($dataObject, 'customer', '');

        if ($customerId === '') {
            return null;
        }

        return Team::query()->where('stripe_id', $customerId)->first();
    }

    protected function resolveTeamById(int $teamId): ?Team
    {
        if ($teamId <= 0) {
            return null;
        }

        return Team::query()->find($teamId);
    }
}
