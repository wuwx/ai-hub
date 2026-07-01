<?php

use App\Enums\TeamRole;
use App\Models\BillingInvoice;
use App\Models\Team;
use App\Models\TeamWallet;
use App\Models\TeamWalletTransaction;
use App\Models\User;
use Laravel\Cashier\Subscription as CashierSubscription;
use Livewire\Livewire;

test('billing page requires authentication', function () {
    $team = Team::factory()->create();

    $response = $this->get(route('billing.index', ['current_team' => $team->slug]));

    $response->assertRedirect(route('login'));
});

test('owners can view billing page', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => TeamRole::Owner->value]);
    $user->switchTeam($team);
    $user->refresh();

    $response = $this
        ->actingAs($user)
        ->get(route('billing.index', ['current_team' => $team->slug]));

    $response->assertOk();
});

test('admins can view billing page', function () {
    $admin = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($admin, ['role' => TeamRole::Admin->value]);
    $admin->switchTeam($team);
    $admin->refresh();

    $response = $this
        ->actingAs($admin)
        ->get(route('billing.index', ['current_team' => $team->slug]));

    $response->assertOk();
});

test('members cannot view billing page', function () {
    $member = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($member, ['role' => TeamRole::Member->value]);
    $member->switchTeam($team);
    $member->refresh();

    $response = $this
        ->actingAs($member)
        ->get(route('billing.index', ['current_team' => $team->slug]));

    $response->assertForbidden();
});

test('billing page shows current plan as free when no subscription', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => TeamRole::Owner->value]);
    $user->switchTeam($team);
    $user->refresh();

    $this->actingAs($user);

    Livewire::test('pages::billing')
        ->assertSee('Free')
        ->assertSee('Choose Your Plan');
});

test('billing page shows active subscription plan', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => TeamRole::Owner->value]);
    $user->switchTeam($team);
    $user->refresh();

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

test('billing page shows wallet balance', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => TeamRole::Owner->value]);
    $user->switchTeam($team);
    $user->refresh();

    TeamWallet::create([
        'team_id' => $team->id,
        'balance_cents' => 5000,
        'credit_grant_cents' => 1000,
        'currency' => 'USD',
        'is_postpaid' => false,
    ]);

    $this->actingAs($user);

    Livewire::test('pages::billing')
        ->assertSee('60.00');
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

test('billing page shows recent invoices', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => TeamRole::Owner->value]);
    $user->switchTeam($team);
    $user->refresh();

    BillingInvoice::create([
        'team_id' => $team->id,
        'invoice_number' => 'INV-2026-001',
        'billing_month' => now()->startOfMonth(),
        'currency' => 'usd',
        'status' => 'paid',
        'subtotal_cents' => 4900,
        'tax_cents' => 0,
        'total_cents' => 4900,
        'issued_at' => now()->startOfMonth(),
        'due_at' => now()->startOfMonth()->addDays(7),
        'paid_at' => now()->startOfMonth()->addDays(2),
    ]);

    $this->actingAs($user);

    Livewire::test('pages::billing')
        ->assertSee('INV-2026-001')
        ->assertSee('49.00');
});

test('billing page shows subscribe button for free plan users', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => TeamRole::Owner->value]);
    $user->switchTeam($team);
    $user->refresh();

    $this->actingAs($user);

    Livewire::test('pages::billing')
        ->assertSee('Subscribe Now');
});

test('billing page shows wallet transactions when available', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => TeamRole::Owner->value]);
    $user->switchTeam($team);
    $user->refresh();

    $wallet = TeamWallet::create([
        'team_id' => $team->id,
        'balance_cents' => 5000,
        'credit_grant_cents' => 1000,
        'currency' => 'USD',
        'is_postpaid' => false,
    ]);

    TeamWalletTransaction::create([
        'team_id' => $team->id,
        'team_wallet_id' => $wallet->id,
        'type' => 'recharge',
        'amount_cents' => 5000,
        'balance_after_cents' => 5000,
        'currency' => 'USD',
        'description' => 'Wallet top-up via Stripe',
    ]);

    $this->actingAs($user);

    Livewire::test('pages::billing')
        ->assertSee('Wallet Transactions')
        ->assertSee('Wallet top-up via Stripe')
        ->assertSee('+50.00');
});

test('billing page shows top up button for managers', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => TeamRole::Owner->value]);
    $user->switchTeam($team);
    $user->refresh();

    $this->actingAs($user);

    Livewire::test('pages::billing')
        ->assertSee('Top Up');
});

test('subscribe to plan creates invoice and attempts checkout', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => TeamRole::Owner->value]);
    $user->switchTeam($team);
    $user->refresh();

    $this->actingAs($user);

    // Stripe not configured, so it will show an error toast
    Livewire::test('pages::billing')
        ->call('subscribeToPlan', 'pro');

    // But an invoice should still be created
    expect(BillingInvoice::where('team_id', $team->id)->count())->toBe(1);
});

test('recharge wallet validates amount', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => TeamRole::Owner->value]);
    $user->switchTeam($team);
    $user->refresh();

    $this->actingAs($user);

    Livewire::test('pages::billing')
        ->set('rechargeAmount', '')
        ->call('rechargeWallet')
        ->assertHasErrors('rechargeAmount');
});
