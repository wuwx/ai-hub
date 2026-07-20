<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Collection;
use Revoltify\Subscriptionify\Models\Plan;

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
     * Find a plan by its code (the subscriptionify slug).
     */
    public function findByCode(string $code): ?Plan
    {
        return Plan::query()->where('slug', $code)->first();
    }

    /**
     * Get the free plan code from config.
     */
    public function freePlanCode(): string
    {
        return (string) config('services.billing.free_plan_code', 'free');
    }
}
