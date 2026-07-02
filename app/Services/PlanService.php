<?php

namespace App\Services;

use App\Models\Plan;
use Illuminate\Database\Eloquent\Collection;

class PlanService
{
    /**
     * Get all active plans ordered by sort_order.
     *
     * @return Collection<int, Plan>
     */
    public function allPlans(): Collection
    {
        return Plan::query()
            ->active()
            ->orderBy('sort_order')
            ->get();
    }

    /**
     * Find a plan by its code.
     */
    public function findByCode(string $code): ?Plan
    {
        return Plan::query()->byCode($code)->first();
    }

    /**
     * Resolve a plan code from a Stripe price ID.
     */
    public function resolveCodeFromPriceId(string $stripePriceId): string
    {
        if ($stripePriceId === '') {
            return $this->freePlanCode();
        }

        $plan = Plan::query()
            ->where('stripe_price_id', $stripePriceId)
            ->first();

        return $plan?->code ?? $this->freePlanCode();
    }

    /**
     * Get the free plan code from config.
     */
    public function freePlanCode(): string
    {
        return (string) config('services.billing.free_plan_code', 'free');
    }
}
