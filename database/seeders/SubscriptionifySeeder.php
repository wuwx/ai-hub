<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Revoltify\Subscriptionify\Enums\FeatureType;
use Revoltify\Subscriptionify\Enums\Interval;
use Revoltify\Subscriptionify\Models\Feature;
use Revoltify\Subscriptionify\Models\Plan;

/**
 * Seed Subscriptionify's feature/plan catalogue used to enforce token quotas.
 *
 * The token limits mirror the capability `plans` table (PlanSeeder) by slug so
 * the two stay in sync: a plan's `code` equals the Subscriptionify plan `slug`.
 */
class SubscriptionifySeeder extends Seeder
{
    /**
     * Token quota features, keyed by slug, with their reset interval.
     *
     * @var array<string, array{interval: Interval, period: int}>
     */
    private const FEATURES = [
        'daily-tokens' => ['interval' => Interval::Day, 'period' => 1],
        'weekly-tokens' => ['interval' => Interval::Week, 'period' => 1],
        'monthly-tokens' => ['interval' => Interval::Month, 'period' => 1],
    ];

    /**
     * Plan definitions: slug => quota definition.
     *
     * @var array<string, array{is_free: bool, limits: array<string, int>}>
     */
    private const PLANS = [
        'free' => [
            'is_free' => true,
            'limits' => [
                'daily-tokens' => 20_000,
                'weekly-tokens' => 120_000,
                'monthly-tokens' => 500_000,
            ],
        ],
        'pro' => [
            'is_free' => false,
            'limits' => [
                'daily-tokens' => 300_000,
                'weekly-tokens' => 2_000_000,
                'monthly-tokens' => 8_000_000,
            ],
        ],
        // A zero value in the feature pivot means "unlimited" in Subscriptionify.
        'enterprise' => [
            'is_free' => false,
            'limits' => [
                'daily-tokens' => 0,
                'weekly-tokens' => 0,
                'monthly-tokens' => 0,
            ],
        ],
    ];

    /**
     * Run the seeder.
     */
    public function run(): void
    {
        foreach (self::FEATURES as $slug => $config) {
            Feature::query()->updateOrCreate(
                ['slug' => $slug],
                [
                    'name' => ucwords(str_replace('-', ' ', $slug)),
                    'type' => FeatureType::Limit,
                    'sort_order' => match ($slug) {
                        'daily-tokens' => 1,
                        'weekly-tokens' => 2,
                        default => 3,
                    },
                ],
            );
        }

        foreach (self::PLANS as $slug => $definition) {
            $this->syncPlan($slug, $definition);
        }
    }

    /**
     * Create or update a plan and attach its token features.
     *
     * @param  array{is_free: bool, limits: array<string, int>}  $definition
     */
    private function syncPlan(string $slug, array $definition): void
    {
        $plan = Plan::query()->updateOrCreate(
            ['slug' => $slug],
            [
                'name' => ucfirst($slug),
                'is_free' => $definition['is_free'],
                'billing_interval' => Interval::Month,
                'billing_period' => 1,
            ],
        );

        foreach ($definition['limits'] as $featureSlug => $value) {
            $featureConfig = self::FEATURES[$featureSlug];
            $feature = Feature::query()->where('slug', $featureSlug)->firstOrFail();

            $plan->features()->syncWithoutDetaching([
                $feature->getKey() => [
                    'value' => (string) $value,
                    'reset_period' => $featureConfig['period'],
                    'reset_interval' => $featureConfig['interval']->value,
                ],
            ]);
        }
    }
}
