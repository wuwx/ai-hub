<?php

use App\Models\Plan;
use App\Models\QuotaPolicy;
use App\Models\User;
use Illuminate\Testing\TestResponse;
use Laravel\Cashier\Subscription as CashierSubscription;

beforeEach(function () {
    config()->set('cashier.webhook.secret', 'whsec_test_123');
    config()->set('cashier.webhook.tolerance', 300);
});

/**
 * Helper to send a signed webhook payload to the Cashier webhook endpoint.
 */
function sendWebhook(
    array $payloadArray,
    string $secret = 'whsec_test_123',
): TestResponse {
    $payload = json_encode($payloadArray, JSON_UNESCAPED_UNICODE);
    $timestamp = now()->timestamp;
    $signature = hash_hmac('sha256', $timestamp.'.'.$payload, $secret);

    return test()->call(
        method: 'POST',
        uri: '/stripe/webhook',
        parameters: [],
        cookies: [],
        files: [],
        server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_STRIPE_SIGNATURE' => 't='.$timestamp.',v1='.$signature,
        ],
        content: $payload,
    );
}

it('rejects stripe webhook request with invalid signature', function () {
    $payload = json_encode(
        [
            'type' => 'customer.subscription.updated',
            'data' => ['object' => ['id' => 'sub_invalid']],
        ],
        JSON_UNESCAPED_UNICODE,
    );

    $response = $this->call(
        method: 'POST',
        uri: '/stripe/webhook',
        parameters: [],
        cookies: [],
        files: [],
        server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_STRIPE_SIGNATURE' => 't='.now()->timestamp.',v1=invalid',
        ],
        content: $payload,
    );

    $response->assertStatus(403);
});

it(
    'syncs user subscription and applies plan quota limits from stripe webhook',
    function () {
        Plan::updateOrCreate(['code' => 'pro'], [
            'name' => 'Pro',
            'stripe_price_id' => 'price_pro_test',
            'daily_token_limit' => 123456,
            'weekly_token_limit' => 654321,
            'monthly_token_limit' => 987654,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $user = User::factory()->create();
        $user->stripe_id = 'cus_123';
        $user->save();

        $response = sendWebhook([
            'type' => 'customer.subscription.updated',
            'data' => [
                'object' => [
                    'id' => 'sub_123',
                    'customer' => 'cus_123',
                    'status' => 'active',
                    'current_period_start' => now()->subDay()->timestamp,
                    'current_period_end' => now()->addMonth()->timestamp,
                    'cancel_at_period_end' => false,
                    'items' => [
                        'data' => [
                            [
                                'id' => 'si_123',
                                'price' => [
                                    'id' => 'price_pro_test',
                                    'product' => 'prod_123',
                                ],
                                'quantity' => 1,
                            ],
                        ],
                    ],
                    'metadata' => [
                        'user_id' => (string) $user->id,
                        'plan_code' => 'pro',
                    ],
                ],
            ],
        ]);

        $response->assertSuccessful();

        // Cashier should have created a subscription record.
        $subscription = CashierSubscription::query()
            ->where('user_id', $user->id)
            ->first();

        expect($subscription)->not->toBeNull();
        expect($subscription->stripe_id)->toBe('sub_123');
        expect($subscription->stripe_status)->toBe('active');
        expect($subscription->stripe_price)->toBe('price_pro_test');

        $activePolicy = QuotaPolicy::query()
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->latest('id')
            ->first();

        expect($activePolicy)->not->toBeNull();
        expect($activePolicy->daily_token_limit)->toBe(123456);
        expect($activePolicy->weekly_token_limit)->toBe(654321);
        expect($activePolicy->monthly_token_limit)->toBe(987654);
    },
);

it(
    'downgrades subscription quota to free when stripe status is past_due',
    function () {
        Plan::updateOrCreate(['code' => 'free'], [
            'name' => 'Free',
            'daily_token_limit' => 20000,
            'weekly_token_limit' => 120000,
            'monthly_token_limit' => 500000,
            'is_active' => true,
            'sort_order' => 0,
        ]);
        Plan::updateOrCreate(['code' => 'pro'], [
            'name' => 'Pro',
            'stripe_price_id' => 'price_pro_test',
            'daily_token_limit' => 123456,
            'weekly_token_limit' => 654321,
            'monthly_token_limit' => 987654,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $user = User::factory()->create();
        $user->stripe_id = 'cus_past_due_001';
        $user->save();

        $response = sendWebhook([
            'type' => 'customer.subscription.updated',
            'data' => [
                'object' => [
                    'id' => 'sub_past_due_001',
                    'customer' => 'cus_past_due_001',
                    'status' => 'past_due',
                    'items' => [
                        'data' => [
                            [
                                'id' => 'si_past_due',
                                'price' => [
                                    'id' => 'price_pro_test',
                                    'product' => 'prod_123',
                                ],
                                'quantity' => 1,
                            ],
                        ],
                    ],
                    'metadata' => [
                        'user_id' => (string) $user->id,
                        'plan_code' => 'pro',
                    ],
                ],
            ],
        ]);

        $response->assertSuccessful();

        // Cashier should have created/updated the subscription record.
        $subscription = CashierSubscription::query()
            ->where('user_id', $user->id)
            ->first();

        expect($subscription)->not->toBeNull();
        expect($subscription->stripe_id)->toBe('sub_past_due_001');
        expect($subscription->stripe_status)->toBe('past_due');

        $activePolicy = QuotaPolicy::query()
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->latest('id')
            ->first();

        expect($activePolicy)->not->toBeNull();
        expect($activePolicy->daily_token_limit)->toBe(20000);
        expect($activePolicy->weekly_token_limit)->toBe(120000);
        expect($activePolicy->monthly_token_limit)->toBe(500000);
    },
);

it(
    'resolves the user from the stripe customer id when metadata is missing',
    function () {
        Plan::updateOrCreate(['code' => 'pro'], [
            'name' => 'Pro',
            'stripe_price_id' => 'price_pro_test',
            'daily_token_limit' => 123456,
            'weekly_token_limit' => 654321,
            'monthly_token_limit' => 987654,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $user = User::factory()->create();
        $user->stripe_id = 'cus_no_metadata';
        $user->save();

        $response = sendWebhook([
            'type' => 'customer.subscription.created',
            'data' => [
                'object' => [
                    'id' => 'sub_no_metadata',
                    'customer' => 'cus_no_metadata',
                    'status' => 'active',
                    'items' => [
                        'data' => [
                            [
                                'id' => 'si_no_metadata',
                                'price' => [
                                    'id' => 'price_pro_test',
                                    'product' => 'prod_123',
                                ],
                                'quantity' => 1,
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $response->assertSuccessful();

        $activePolicy = QuotaPolicy::query()
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->latest('id')
            ->first();

        expect($activePolicy)->not->toBeNull();
        expect($activePolicy->plan_code)->toBe('pro');
    },
);
