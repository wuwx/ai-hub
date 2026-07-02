<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    /**
     * Seed the plans table with default plan definitions.
     */
    public function run(): void
    {
        $plans = [
            [
                'code' => 'free',
                'name' => 'Free',
                'description' => 'For personal projects and evaluation',
                'monthly_price_cents' => 0,
                'stripe_price_id' => 'price_free',
                'daily_token_limit' => 20_000,
                'weekly_token_limit' => 120_000,
                'monthly_token_limit' => 500_000,
                'features' => [
                    '20K daily tokens',
                    '500K monthly tokens',
                    'All LLM providers',
                    'Community support',
                ],
                'is_active' => true,
                'sort_order' => 0,
            ],
            [
                'code' => 'pro',
                'name' => 'Pro',
                'description' => 'For professionals and growing teams',
                'monthly_price_cents' => 4900,
                'stripe_price_id' => 'price_pro',
                'daily_token_limit' => 300_000,
                'weekly_token_limit' => 2_000_000,
                'monthly_token_limit' => 8_000_000,
                'features' => [
                    '300K daily tokens',
                    '8M monthly tokens',
                    'All LLM providers',
                    'Priority support',
                    'Advanced analytics',
                ],
                'is_active' => true,
                'sort_order' => 1,
            ],
            [
                'code' => 'enterprise',
                'name' => 'Enterprise',
                'description' => 'For organizations with advanced needs',
                'monthly_price_cents' => 19900,
                'stripe_price_id' => 'price_enterprise',
                'daily_token_limit' => null,
                'weekly_token_limit' => null,
                'monthly_token_limit' => null,
                'features' => [
                    'Unlimited tokens',
                    'All LLM providers',
                    'Dedicated support',
                    'Custom integrations',
                    'SLA guarantee',
                ],
                'is_active' => true,
                'sort_order' => 2,
            ],
        ];

        foreach ($plans as $plan) {
            Plan::updateOrCreate(
                ['code' => $plan['code']],
                $plan,
            );
        }
    }
}
