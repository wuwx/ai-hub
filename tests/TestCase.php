<?php

namespace Tests;

use App\Models\LlmModel;
use App\Models\LlmProvider;
use App\Models\User;
use Database\Seeders\SubscriptionifySeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Laravel\Fortify\Features;
use Revoltify\Subscriptionify\Enums\FeatureType;
use Revoltify\Subscriptionify\Enums\Interval;
use Revoltify\Subscriptionify\Models\Feature;
use Revoltify\Subscriptionify\Models\Plan;
use Revoltify\Subscriptionify\Services\FeatureResolver;

abstract class TestCase extends BaseTestCase
{
    /**
     * Token quota feature slugs and their reset intervals.
     *
     * @return array<string, Interval>
     */
    protected function tokenFeatures(): array
    {
        return [
            'daily-tokens' => Interval::Day,
            'weekly-tokens' => Interval::Week,
            'monthly-tokens' => Interval::Month,
        ];
    }

    protected function skipUnlessFortifyHas(string $feature, ?string $message = null): void
    {
        if (! Features::enabled($feature)) {
            $this->markTestSkipped($message ?? "Fortify feature [{$feature}] is not enabled.");
        }
    }

    /**
     * Seed Subscriptionify's feature/plan catalogue (mirrors SubscriptionifySeeder).
     */
    protected function seedSubscriptionify(): void
    {
        (new SubscriptionifySeeder)->run();
    }

    /**
     * Ensure the three token-quota features exist.
     */
    protected function ensureTokenFeatures(): void
    {
        foreach ($this->tokenFeatures() as $slug => $interval) {
            Feature::query()->updateOrCreate(
                ['slug' => $slug],
                [
                    'name' => ucwords(str_replace('-', ' ', $slug)),
                    'type' => FeatureType::Limit,
                ],
            );
        }
    }

    /**
     * Create (or update) a Subscriptionify plan with the given token limits.
     *
     * @param  array<string, int>  $limits
     */
    protected function makeSubscriptionifyPlan(string $slug, array $limits): void
    {
        $this->ensureTokenFeatures();

        $plan = Plan::query()->updateOrCreate(
            ['slug' => $slug],
            [
                'name' => ucfirst($slug),
                'is_free' => $slug === 'free',
                'billing_interval' => Interval::Month,
                'billing_period' => 1,
            ],
        );

        foreach ($limits as $featureSlug => $value) {
            $interval = $this->tokenFeatures()[$featureSlug];
            $feature = Feature::query()->where('slug', $featureSlug)->firstOrFail();

            $plan->features()->syncWithoutDetaching([
                $feature->getKey() => [
                    'value' => (string) $value,
                    'reset_period' => 1,
                    'reset_interval' => $interval->value,
                ],
            ]);
        }
    }

    /**
     * Subscribe (or move) a user onto the given plan code and flush the
     * Subscriptionify feature cache so quota limits reflect within the test.
     */
    public static function subscribeUserToPlan(User $user, string $planCode): User
    {
        $plan = Plan::query()->where('slug', $planCode)->firstOrFail();

        if ($user->subscribed()) {
            $user->subscription()->changePlan($plan, resetUsages: false);
        } else {
            $user->subscribe($plan);
        }

        resolve(FeatureResolver::class)->flush();

        return $user;
    }

    /**
     * Subscribe a user to the free plan so the Subscriptionify-backed quota
     * path (plan resolution, model entitlements) is exercised in tests.
     */
    public static function subscribeUserToFreePlan(User $user): User
    {
        (new SubscriptionifySeeder)->run();

        return self::subscribeUserToPlan(
            $user,
            (string) config('services.billing.free_plan_code', 'free'),
        );
    }

    /**
     * Grant a plan access to an LLM model via a Subscriptionify toggle feature.
     */
    public static function entitleModel(LlmModel $model, string $planCode = 'free'): void
    {
        (new SubscriptionifySeeder)->run();

        $feature = Feature::query()->updateOrCreate(
            ['slug' => 'model:'.$model->external_model_id],
            ['name' => $model->name.' access', 'type' => FeatureType::Toggle],
        );

        Plan::query()->where('slug', $planCode)->firstOrFail()
            ->features()->syncWithoutDetaching([$feature->getKey() => ['value' => '1']]);

        resolve(FeatureResolver::class)->flush();
    }

    /**
     * Grant a plan access to an LLM provider via a Subscriptionify toggle feature.
     */
    public static function entitleProvider(LlmProvider $provider, string $planCode = 'free'): void
    {
        (new SubscriptionifySeeder)->run();

        $feature = Feature::query()->updateOrCreate(
            ['slug' => 'provider:'.$provider->slug],
            ['name' => $provider->name.' access', 'type' => FeatureType::Toggle],
        );

        Plan::query()->where('slug', $planCode)->firstOrFail()
            ->features()->syncWithoutDetaching([$feature->getKey() => ['value' => '1']]);

        resolve(FeatureResolver::class)->flush();
    }

    /**
     * Grant a user arbitrary token quotas via direct Subscriptionify features.
     */
    protected function grantQuota(
        User $user,
        ?int $daily = null,
        ?int $weekly = null,
        ?int $monthly = null,
    ): User {
        $this->ensureTokenFeatures();

        $limits = ['daily-tokens' => $daily, 'weekly-tokens' => $weekly, 'monthly-tokens' => $monthly];

        foreach ($limits as $slug => $value) {
            if ($value === null) {
                continue;
            }

            $interval = $this->tokenFeatures()[$slug];

            $user->grantFeature($slug, value: $value, resetPeriod: 1, resetInterval: $interval);
        }

        return $user;
    }
}
