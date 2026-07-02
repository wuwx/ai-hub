<?php

namespace App\Http\Controllers\Billing;

use App\Actions\Billing\SyncQuotaFromSubscription;
use App\Models\User;
use App\Services\PlanService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Handle the user redirect back from Stripe Checkout.
 *
 * Stripe appends ?session_id=cs_xxx to the success URL. We use this to
 * retrieve the session from Stripe and confirm the subscription locally —
 * this acts as a safety net when the webhook has not yet arrived (common in
 * local development or when the webhook endpoint is unreachable).
 */
class CheckoutReturnController
{
    public function __construct(
        private readonly SyncQuotaFromSubscription $syncQuotaFromSubscription,
        private readonly PlanService $planService,
    ) {}

    /**
     * Confirm a subscription checkout session.
     */
    public function subscription(Request $request)
    {
        $user = $request->user();
        $sessionId = $request->query('session_id');

        if ($sessionId && $user) {
            $this->confirmSubscriptionSession($user, $sessionId);
        }

        return to_route('billing.index');
    }

    /**
     * Confirm a subscription from a Stripe checkout session and sync the
     * team's quota policy immediately, ahead of the webhook.
     */
    protected function confirmSubscriptionSession(
        User $user,
        string $sessionId,
    ): void {
        try {
            $session = $user
                ->stripe()
                ->checkout->sessions->retrieve($sessionId, [
                    'expand' => ['subscription'],
                ]);
        } catch (\Exception $e) {
            Log::warning(
                'Failed to retrieve Stripe checkout session on return',
                ['session_id' => $sessionId, 'error' => $e->getMessage()],
            );

            return;
        }

        $subscription = $session->subscription ?? null;

        if (! is_object($subscription)) {
            return;
        }

        $stripePriceId = (string) data_get(
            $subscription,
            'items.data.0.price.id',
            '',
        );
        $planCode = $this->planService->resolveCodeFromPriceId($stripePriceId);

        $this->syncQuotaFromSubscription->handle(
            user: $user,
            planCode: $planCode,
            status: (string) ($subscription->status ?? 'active'),
        );
    }
}
