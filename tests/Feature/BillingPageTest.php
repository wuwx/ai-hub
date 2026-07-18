<?php

use App\Actions\Billing\SyncQuotaFromSubscription;
use App\Models\Plan;
use App\Models\QuotaPolicy;
use App\Models\User;
use Laravel\Cashier\Subscription as CashierSubscription;
use Laravel\Cashier\SubscriptionItem as CashierSubscriptionItem;
use Livewire\Livewire;
use Stripe\Checkout\Session as StripeSession;
use Stripe\Customer;
use Stripe\StripeClient;
use Stripe\Subscription as StripeSubscriptionObject;

/**
 * Build a fake Stripe client (duck-typed, no Cashier/return-type constraints)
 * that satisfies the calls made when starting a new subscription checkout.
 */
function fakeStripeClientForNewSubscriptionCheckout(
    string $sessionId,
    string $sessionUrl,
    string $customerId = 'cus_test_billing',
): StripeClient {
    $customer = Customer::constructFrom(['id' => $customerId]);
    $session = StripeSession::constructFrom([
        'id' => $sessionId,
        'url' => $sessionUrl,
    ]);

    $customersService = new class($customer)
    {
        public function __construct(private Customer $customer) {}

        public function retrieve(...$args)
        {
            return $this->customer;
        }

        public function create(...$args)
        {
            return $this->customer;
        }
    };

    $sessionsService = new class($session)
    {
        public function __construct(private StripeSession $session) {}

        public function create(...$args)
        {
            return $this->session;
        }
    };

    $checkoutService = new class($sessionsService)
    {
        public function __construct(public $sessions) {}
    };

    return new class($customersService, $checkoutService) extends StripeClient
    {
        public function __construct(public $customers, public $checkout)
        {
            //
        }
    };
}

/**
 * Build a fake Stripe client that satisfies the calls made when swapping an
 * existing subscription to a different price.
 */
function fakeStripeClientForSubscriptionSwap(
    string $stripeSubscriptionId,
    string $currentPriceId,
    string $newPriceId,
): StripeClient {
    $currentSubscription = StripeSubscriptionObject::constructFrom([
        'id' => $stripeSubscriptionId,
        'status' => 'active',
        'items' => [
            'object' => 'list',
            'data' => [
                [
                    'id' => 'si_original',
                    'price' => [
                        'id' => $currentPriceId,
                        'product' => 'prod_pro',
                        'recurring' => ['usage_type' => 'licensed'],
                    ],
                    'quantity' => 1,
                ],
            ],
        ],
    ]);

    $swappedSubscription = StripeSubscriptionObject::constructFrom([
        'id' => $stripeSubscriptionId,
        'status' => 'active',
        'items' => [
            'object' => 'list',
            'data' => [
                [
                    'id' => 'si_swapped',
                    'price' => [
                        'id' => $newPriceId,
                        'product' => 'prod_swapped',
                    ],
                    'quantity' => 1,
                ],
            ],
        ],
    ]);

    $subscriptionsService = new class($currentSubscription, $swappedSubscription)
    {
        public function __construct(
            private $currentSubscription,
            private $swappedSubscription,
        ) {}

        public function retrieve(...$args)
        {
            return $this->currentSubscription;
        }

        public function update(...$args)
        {
            return $this->swappedSubscription;
        }
    };

    return new class($subscriptionsService) extends StripeClient
    {
        public function __construct(public $subscriptions)
        {
            //
        }
    };
}

test('billing page requires authentication', function () {

    $response = $this->get(
        route('billing.index'),
    );

    $response->assertRedirect(route('login'));
});

test('authenticated users can view billing page', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(
        route('billing.index'),
    );

    $response->assertOk();
});

test(
    'billing page shows current plan as free when no subscription',
    function () {
        $user = User::factory()->create();

        $this->actingAs($user);

        Livewire::test('pages::billing')
            ->assertSee('Free')
            ->assertSee('Choose Your Plan');
    },
);

test('billing page shows active subscription plan', function () {
    $user = User::factory()->create();

    Plan::updateOrCreate(['code' => 'pro'], [
        'name' => 'Pro',
        'stripe_price_id' => 'price_pro',
        'monthly_price_cents' => 4900,
        'daily_token_limit' => 300000,
        'weekly_token_limit' => 2000000,
        'monthly_token_limit' => 8000000,
        'is_active' => true,
        'sort_order' => 1,
    ]);

    CashierSubscription::create([
        'user_id' => $user->id,
        'type' => 'default',
        'stripe_id' => 'sub_test123',
        'stripe_status' => 'active',
        'stripe_price' => 'price_pro',
        'quantity' => 1,
    ]);

    $this->actingAs($user);

    Livewire::test('pages::billing')
        ->assertSee('Pro')
        ->assertSee('Current Plan');
});

test('billing page displays all available plans', function () {
    $user = User::factory()->create();

    Plan::updateOrCreate(['code' => 'free'], ['name' => 'Free', 'monthly_price_cents' => 0, 'is_active' => true, 'sort_order' => 0]);
    Plan::updateOrCreate(['code' => 'pro'], ['name' => 'Pro', 'monthly_price_cents' => 4900, 'is_active' => true, 'sort_order' => 1]);
    Plan::updateOrCreate(['code' => 'enterprise'], ['name' => 'Enterprise', 'monthly_price_cents' => 19900, 'is_active' => true, 'sort_order' => 2]);

    $this->actingAs($user);

    Livewire::test('pages::billing')
        ->assertSee('Free')
        ->assertSee('Pro')
        ->assertSee('Enterprise');
});

test('billing page shows subscribe button for free plan users', function () {
    Plan::updateOrCreate(['code' => 'free'], ['name' => 'Free', 'monthly_price_cents' => 0, 'is_active' => true, 'sort_order' => 0]);
    Plan::updateOrCreate(['code' => 'pro'], ['name' => 'Pro', 'monthly_price_cents' => 4900, 'stripe_price_id' => 'price_pro', 'is_active' => true, 'sort_order' => 1]);

    $user = User::factory()->create();

    $this->actingAs($user);

    Livewire::test('pages::billing')->assertSee('Subscribe Now');
});

test(
    'subscribing a free-plan team to a paid plan starts a stripe checkout session',
    function () {
        config()->set('cashier.secret', 'sk_test_123');

        Plan::updateOrCreate(['code' => 'pro'], [
            'name' => 'Pro',
            'stripe_price_id' => 'price_pro',
            'monthly_price_cents' => 4900,
            'daily_token_limit' => 300000,
            'weekly_token_limit' => 2000000,
            'monthly_token_limit' => 8000000,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $user = User::factory()->create();

        app()->bind(
            StripeClient::class,
            fn () => fakeStripeClientForNewSubscriptionCheckout(
                'cs_test_sub_1',
                'https://checkout.stripe.com/pay/cs_test_sub_1',
            ),
        );

        $this->actingAs($user);

        Livewire::test('pages::billing')
            ->call('subscribeToPlan', 'pro')
            ->assertRedirect('https://checkout.stripe.com/pay/cs_test_sub_1');
    },
);

test('switching to the free plan syncs quota back to free limits', function () {
    Plan::updateOrCreate(['code' => 'free'], [
        'name' => 'Free',
        'monthly_price_cents' => 0,
        'daily_token_limit' => 20000,
        'weekly_token_limit' => 120000,
        'monthly_token_limit' => 500000,
        'is_active' => true,
        'sort_order' => 0,
    ]);
    Plan::updateOrCreate(['code' => 'pro'], [
        'name' => 'Pro',
        'stripe_price_id' => 'price_pro',
        'monthly_price_cents' => 4900,
        'daily_token_limit' => 300000,
        'weekly_token_limit' => 2000000,
        'monthly_token_limit' => 8000000,
        'is_active' => true,
        'sort_order' => 1,
    ]);

    $user = User::factory()->create();

    // User is on the Pro plan's quota, but has no live Cashier subscription
    // record (e.g. it was provisioned manually) — downgrading should not
    // need to call Stripe at all.
    app(SyncQuotaFromSubscription::class)->handle(
        user: $user,
        planCode: 'pro',
        status: 'active',
    );

    $this->actingAs($user);

    Livewire::test('pages::billing')->call('subscribeToPlan', 'free');

    $activePolicy = QuotaPolicy::query()
        ->where('user_id', $user->id)
        ->where('is_active', true)
        ->first();

    expect($activePolicy->plan_code)->toBe('free');
    expect($activePolicy->daily_token_limit)->toBe(20000);
});

    test(
        'switching plans while already subscribed swaps the stripe price in place',
        function () {
            config()->set('cashier.secret', 'sk_test_swap');

            Plan::updateOrCreate(['code' => 'pro'], [
            'name' => 'Pro',
            'stripe_price_id' => 'price_pro',
            'monthly_price_cents' => 4900,
            'daily_token_limit' => 300000,
            'weekly_token_limit' => 2000000,
            'monthly_token_limit' => 8000000,
            'is_active' => true,
            'sort_order' => 1,
        ]);
        Plan::updateOrCreate(['code' => 'enterprise'], [
            'name' => 'Enterprise',
            'stripe_price_id' => 'price_enterprise',
            'monthly_price_cents' => 19900,
            'daily_token_limit' => null,
            'weekly_token_limit' => null,
            'monthly_token_limit' => null,
            'is_active' => true,
            'sort_order' => 2,
        ]);

        $user = User::factory()->create();

        $subscription = CashierSubscription::create([
            'user_id' => $user->id,
            'type' => 'default',
            'stripe_id' => 'sub_swap_test',
            'stripe_status' => 'active',
            'stripe_price' => 'price_pro',
            'quantity' => 1,
        ]);

        CashierSubscriptionItem::create([
            'subscription_id' => $subscription->id,
            'stripe_id' => 'si_original',
            'stripe_product' => 'prod_pro',
            'stripe_price' => 'price_pro',
            'quantity' => 1,
        ]);

        app(SyncQuotaFromSubscription::class)->handle(
            user: $user,
            planCode: 'pro',
            status: 'active',
        );

        app()->bind(
            StripeClient::class,
            fn () => fakeStripeClientForSubscriptionSwap(
                'sub_swap_test',
                'price_pro',
                'price_enterprise',
            ),
        );

        $this->actingAs($user);

        Livewire::test('pages::billing')->call('subscribeToPlan', 'enterprise');

        $subscription->refresh();
        expect($subscription->stripe_price)->toBe('price_enterprise');

        $activePolicy = QuotaPolicy::query()
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->first();

        expect($activePolicy->plan_code)->toBe('enterprise');
    },
);
