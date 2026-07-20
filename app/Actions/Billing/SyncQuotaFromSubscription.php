<?php

namespace App\Actions\Billing;

use App\Models\User;
use Carbon\CarbonInterface;
use Revoltify\Subscriptionify\Models\Plan;
use Revoltify\Subscriptionify\Services\FeatureResolver;

class SyncQuotaFromSubscription
{
    /**
     * Provision (or update) the user's quota subscription from a plan code.
     *
     * The {@see User} is itself the Subscriptionify subscribable, so it is
     * subscribed to (or moved onto) the matching plan directly; the plan's
     * attached token features drive quota enforcement.
     */
    public function handle(
        User $user,
        string $planCode,
        string $status = 'active',
        ?CarbonInterface $at = null,
    ): User {
        $at ??= now();

        if (! in_array(strtolower($status), ['active', 'trialing'], true)) {
            $planCode = (string) config(
                'services.billing.free_plan_code',
                'free',
            );
        }

        $plan = $this->resolvePlan($planCode);

        if ($user->subscribed()) {
            $user->subscription()->changePlan($plan, resetUsages: false);
        } else {
            $user->subscribe($plan);
        }

        // The FeatureResolver is a singleton; clear its cache so feature
        // limits reflect the new plan within the same request.
        resolve(FeatureResolver::class)->flush();

        return $user;
    }

    protected function resolvePlan(string $planCode): Plan
    {
        $plan = Plan::query()->where('slug', $planCode)->first();

        if ($plan instanceof Plan) {
            return $plan;
        }

        $freeCode = (string) config('services.billing.free_plan_code', 'free');

        return Plan::query()->where('slug', $freeCode)->firstOrFail();
    }
}
