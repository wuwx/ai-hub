<?php

use App\Models\User;
use Livewire\Livewire;
use Tests\TestCase;

test('billing page requires authentication', function () {
    $response = $this->get(route('billing.index'));

    $response->assertRedirect(route('login'));
});

test('authenticated users can view billing page', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('billing.index'));

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

    $this->makeSubscriptionifyPlan('pro', []);

    // Provision the user's subscriptionify subscription directly.
    TestCase::subscribeUserToPlan($user, 'pro');

    $this->actingAs($user);

    Livewire::test('pages::billing')
        ->assertSee('Pro')
        ->assertSee('Current Plan');
});

test('billing page displays all available plans', function () {
    $user = User::factory()->create();

    $this->makeSubscriptionifyPlan('free', []);
    $this->makeSubscriptionifyPlan('pro', []);
    $this->makeSubscriptionifyPlan('enterprise', []);

    $this->actingAs($user);

    Livewire::test('pages::billing')
        ->assertSee('Free')
        ->assertSee('Pro')
        ->assertSee('Enterprise');
});

test('billing page shows subscribe button for free plan users', function () {
    $this->makeSubscriptionifyPlan('free', []);
    $this->makeSubscriptionifyPlan('pro', []);

    $user = User::factory()->create();

    $this->actingAs($user);

    Livewire::test('pages::billing')->assertSee('Subscribe Now');
});

test('switching to the free plan syncs quota back to free limits', function () {
    // The Subscriptionify plan catalogue drives quota enforcement.
    $this->seedSubscriptionify();

    $user = User::factory()->create();

    // User is on the Pro plan's quota, but has no live Stripe subscription
    // record (e.g. it was provisioned manually) — downgrading should not
    // need to call Stripe at all.
    TestCase::subscribeUserToPlan($user, 'pro');

    $this->actingAs($user);

    Livewire::test('pages::billing')->call('subscribeToPlan', 'free');

    expect($user->subscription())->not->toBeNull();
    expect($user->subscription()->plan->slug)->toBe('free');
    expect((int) $user->featureInfo('daily-tokens')->limit)->toBe(20000);
    expect((int) $user->featureInfo('weekly-tokens')->limit)->toBe(120000);
    expect((int) $user->featureInfo('monthly-tokens')->limit)->toBe(500000);
});
