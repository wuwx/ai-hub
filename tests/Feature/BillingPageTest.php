<?php

use App\Actions\Billing\SyncTeamQuotaFromSubscription;
use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\TeamQuotaPolicy;
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
    $team = Team::factory()->create();

    $response = $this->get(
        route('billing.index', ['current_team' => $team->slug]),
    );

    $response->assertRedirect(route('login'));
});

test('owners can view billing page', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => TeamRole::Owner->value]);
    $user->switchTeam($team);
    $user->refresh();

    $response = $this->actingAs($user)->get(
        route('billing.index', ['current_team' => $team->slug]),
    );

    $response->assertOk();
});

test('admins can view billing page', function () {
    $admin = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($admin, ['role' => TeamRole::Admin->value]);
    $admin->switchTeam($team);
    $admin->refresh();

    $response = $this->actingAs($admin)->get(
        route('billing.index', ['current_team' => $team->slug]),
    );

    $response->assertOk();
});

test('members cannot view billing page', function () {
    $member = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($member, ['role' => TeamRole::Member->value]);
    $member->switchTeam($team);
    $member->refresh();

    $response = $this->actingAs($member)->get(
        route('billing.index', ['current_team' => $team->slug]),
    );

    $response->assertForbidden();
});

test(
    'billing page shows current plan as free when no subscription',
    function () {
        $user = User::factory()->create();
        $team = Team::factory()->create();
        $team->members()->attach($user, ['role' => TeamRole::Owner->value]);
        $user->switchTeam($team);
        $user->refresh();

        $this->actingAs($user);

        Livewire::test('pages::billing')
            ->assertSee('Free')
            ->assertSee('Choose Your Plan');
    },
);

test('billing page shows active subscription plan', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => TeamRole::Owner->value]);
    $user->switchTeam($team);
    $user->refresh();

    config()->set('services.billing.plans.pro.stripe_price_id', 'price_pro');

    CashierSubscription::create([
        'team_id' => $team->id,
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
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => TeamRole::Owner->value]);
    $user->switchTeam($team);
    $user->refresh();

    $this->actingAs($user);

    Livewire::test('pages::billing')
        ->assertSee('Free')
        ->assertSee('Pro')
        ->assertSee('Enterprise');
});

test('billing page shows subscribe button for free plan users', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => TeamRole::Owner->value]);
    $user->switchTeam($team);
    $user->refresh();

    $this->actingAs($user);

    Livewire::test('pages::billing')->assertSee('Subscribe Now');
});

test(
    'subscribing a free-plan team to a paid plan starts a stripe checkout session',
    function () {
        config()->set('cashier.secret', 'sk_test_123');
        config()->set(
            'services.billing.plans.pro.stripe_price_id',
            'price_pro',
        );
        config()->set('services.billing.plans.pro.monthly_price_cents', 4900);

        $user = User::factory()->create();
        $team = Team::factory()->create(['stripe_id' => 'cus_test_billing']);
        $team->members()->attach($user, ['role' => TeamRole::Owner->value]);
        $user->switchTeam($team);
        $user->refresh();

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
    config()->set('services.billing.free_plan_code', 'free');
    config()->set('services.billing.plans.free', [
        'name' => 'Free',
        'monthly_price_cents' => 0,
        'daily_token_limit' => 20000,
        'weekly_token_limit' => 120000,
        'monthly_token_limit' => 500000,
    ]);
    config()->set('services.billing.plans.pro', [
        'name' => 'Pro',
        'stripe_price_id' => 'price_pro',
        'monthly_price_cents' => 4900,
        'daily_token_limit' => 300000,
        'weekly_token_limit' => 2000000,
        'monthly_token_limit' => 8000000,
    ]);

    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => TeamRole::Owner->value]);
    $user->switchTeam($team);
    $user->refresh();

    // Team is on the Pro plan's quota, but has no live Cashier subscription
    // record (e.g. it was provisioned manually) — downgrading should not
    // need to call Stripe at all.
    app(SyncTeamQuotaFromSubscription::class)->handle(
        team: $team,
        planCode: 'pro',
        status: 'active',
    );

    $this->actingAs($user);

    Livewire::test('pages::billing')->call('subscribeToPlan', 'free');

    $activePolicy = TeamQuotaPolicy::query()
        ->where('team_id', $team->id)
        ->where('is_active', true)
        ->first();

    expect($activePolicy->plan_code)->toBe('free');
    expect($activePolicy->daily_token_limit)->toBe(20000);
});

test(
    'switching plans while already subscribed swaps the stripe price in place',
    function () {
        config()->set('services.billing.free_plan_code', 'free');
        config()->set('services.billing.plans.pro', [
            'name' => 'Pro',
            'stripe_price_id' => 'price_pro',
            'monthly_price_cents' => 4900,
            'daily_token_limit' => 300000,
            'weekly_token_limit' => 2000000,
            'monthly_token_limit' => 8000000,
        ]);
        config()->set('services.billing.plans.enterprise', [
            'name' => 'Enterprise',
            'stripe_price_id' => 'price_enterprise',
            'monthly_price_cents' => 19900,
            'daily_token_limit' => null,
            'weekly_token_limit' => null,
            'monthly_token_limit' => null,
        ]);

        $user = User::factory()->create();
        $team = Team::factory()->create(['stripe_id' => 'cus_test_swap']);
        $team->members()->attach($user, ['role' => TeamRole::Owner->value]);
        $user->switchTeam($team);
        $user->refresh();

        $subscription = CashierSubscription::create([
            'team_id' => $team->id,
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

        app(SyncTeamQuotaFromSubscription::class)->handle(
            team: $team,
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

        $activePolicy = TeamQuotaPolicy::query()
            ->where('team_id', $team->id)
            ->where('is_active', true)
            ->first();

        expect($activePolicy->plan_code)->toBe('enterprise');
    },
);
