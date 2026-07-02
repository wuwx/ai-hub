<?php

use App\Models\UsageLedger;
use App\Models\User;
use Laravel\Cashier\Subscription as CashierSubscription;
use Livewire\Livewire;

test('dashboard page requires authentication', function () {

    $response = $this->get(route('dashboard'));

    $response->assertRedirect(route('login'));
});

test('authenticated users can view dashboard', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->get(route('dashboard'));

    $response->assertOk();
});

test('dashboard shows current plan badge', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    Livewire::test('pages::dashboard')
        ->assertSee('Free')
        ->assertSee('Plan');
});

test('dashboard shows active subscription plan in badge', function () {
    $user = User::factory()->create();

    CashierSubscription::create([
        'user_id' => $user->id,
        'type' => 'default',
        'stripe_id' => 'sub_test123',
        'stripe_status' => 'active',
        'stripe_price' => 'price_pro',
        'quantity' => 1,
    ]);

    $this->actingAs($user);

    Livewire::test('pages::dashboard')
        ->assertSee('Pro');
});

test('dashboard displays stat cards with zero data when no usage', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    Livewire::test('pages::dashboard')
        ->assertSee("Today's Tokens")
        ->assertSee("Today's Requests")
        ->assertSee('Monthly Tokens')
        ->assertSee('Error Rate');
});

test('dashboard displays real usage data from usage ledger', function () {
    $user = User::factory()->create();

    // Create usage ledger entries
    UsageLedger::create([
        'user_id' => $user->id,
        'bucket_type' => 'day',
        'bucket_date' => now()->toDateString(),
        'token_input' => 500,
        'token_output' => 300,
        'token_total' => 800,
        'request_count' => 5,
        'error_count' => 0,
    ]);

    $this->actingAs($user);

    Livewire::test('pages::dashboard')
        ->assertSee('800'); // Today's tokens
});

test('dashboard shows quick actions section', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    Livewire::test('pages::dashboard')
        ->assertSee('Quick Actions')
        ->assertSee('Manage API Keys')
        ->assertSee('View Usage')
        ->assertSee('Billing & Subscription');
});

test('dashboard shows request trend chart section', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    Livewire::test('pages::dashboard')
        ->assertSee('Request Trend (14 days)');
});
