<?php

use App\Actions\Billing\SyncQuotaFromSubscription;
use App\Models\User;
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

    $this->makeSubscriptionifyPlan('pro', []);

    app(SyncQuotaFromSubscription::class)->handle(
        user: $user,
        planCode: 'pro',
        status: 'active',
    );

    $this->actingAs($user);

    Livewire::test('pages::dashboard')
        ->assertSee('Pro');
});

test('dashboard shows quick actions section', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    Livewire::test('pages::dashboard')
        ->assertSee('Quick Actions')
        ->assertSee('Manage API Keys')
        ->assertSee('Billing & Subscription');
});
