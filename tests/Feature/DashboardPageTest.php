<?php

use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\TeamBillingSubscription;
use App\Models\UsageLedger;
use App\Models\User;
use Livewire\Livewire;

test('dashboard page requires authentication', function () {
    $team = Team::factory()->create();

    $response = $this->get(route('dashboard', ['current_team' => $team->slug]));

    $response->assertRedirect(route('login'));
});

test('authenticated users can view dashboard', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => TeamRole::Owner->value]);
    $user->switchTeam($team);
    $user->refresh();

    $response = $this
        ->actingAs($user)
        ->get(route('dashboard', ['current_team' => $team->slug]));

    $response->assertOk();
});

test('dashboard shows current plan badge', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => TeamRole::Owner->value]);
    $user->switchTeam($team);
    $user->refresh();

    $this->actingAs($user);

    Livewire::test('pages::dashboard')
        ->assertSee('Free')
        ->assertSee('Plan');
});

test('dashboard shows active subscription plan in badge', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => TeamRole::Owner->value]);
    $user->switchTeam($team);
    $user->refresh();

    TeamBillingSubscription::create([
        'team_id' => $team->id,
        'payment_provider' => 'stripe',
        'stripe_customer_id' => 'cus_test123',
        'stripe_subscription_id' => 'sub_test123',
        'plan_code' => 'pro',
        'status' => 'active',
        'cancel_at_period_end' => false,
        'current_period_start' => now()->startOfMonth(),
        'current_period_end' => now()->endOfMonth(),
    ]);

    $this->actingAs($user);

    Livewire::test('pages::dashboard')
        ->assertSee('Pro');
});

test('dashboard displays stat cards with zero data when no usage', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => TeamRole::Owner->value]);
    $user->switchTeam($team);
    $user->refresh();

    $this->actingAs($user);

    Livewire::test('pages::dashboard')
        ->assertSee("Today's Tokens")
        ->assertSee("Today's Requests")
        ->assertSee('Monthly Tokens')
        ->assertSee('Error Rate');
});

test('dashboard displays real usage data from usage ledger', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => TeamRole::Owner->value]);
    $user->switchTeam($team);
    $user->refresh();

    // Create usage ledger entries
    UsageLedger::create([
        'team_id' => $team->id,
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
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => TeamRole::Owner->value]);
    $user->switchTeam($team);
    $user->refresh();

    $this->actingAs($user);

    Livewire::test('pages::dashboard')
        ->assertSee('Quick Actions')
        ->assertSee('Manage API Keys')
        ->assertSee('View Usage')
        ->assertSee('Billing & Subscription');
});

test('dashboard shows request trend chart section', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => TeamRole::Owner->value]);
    $user->switchTeam($team);
    $user->refresh();

    $this->actingAs($user);

    Livewire::test('pages::dashboard')
        ->assertSee('Request Trend (14 days)');
});
