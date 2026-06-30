<?php

use App\Enums\TeamRole;
use App\Models\LlmModel;
use App\Models\LlmProvider;
use App\Models\Team;
use App\Models\TeamBillingSubscription;
use App\Models\UsageLedger;
use App\Models\User;
use Livewire\Livewire;

test('usage page requires authentication', function () {
    $team = Team::factory()->create();

    $response = $this->get(route('usage.index', ['current_team' => $team->slug]));

    $response->assertRedirect(route('login'));
});

test('usage page can be rendered by owners', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => TeamRole::Owner->value]);
    $user->switchTeam($team);
    $user->refresh();

    $response = $this
        ->actingAs($user)
        ->get(route('usage.index', ['current_team' => $team->slug]));

    $response->assertOk();
});

test('usage page can be rendered by admins', function () {
    $owner = User::factory()->create();
    $admin = User::factory()->create();
    $team = Team::factory()->create();

    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $team->members()->attach($admin, ['role' => TeamRole::Admin->value]);
    $admin->switchTeam($team);
    $admin->refresh();

    $response = $this
        ->actingAs($admin)
        ->get(route('usage.index', ['current_team' => $team->slug]));

    $response->assertOk();
});

test('members cannot view usage data', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $team = Team::factory()->create();

    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $team->members()->attach($member, ['role' => TeamRole::Member->value]);
    $member->switchTeam($team);
    $member->refresh();

    $this->actingAs($member);

    $component = Livewire::test('pages::usage');

    expect($component->instance()->canView)->toBeFalse();
});

test('usage page shows billing cycle from subscription', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => TeamRole::Owner->value]);
    $user->switchTeam($team);
    $user->refresh();

    TeamBillingSubscription::create([
        'team_id' => $team->id,
        'payment_provider' => 'stripe',
        'plan_code' => 'pro',
        'status' => 'active',
        'current_period_start' => '2026-06-01',
        'current_period_end' => '2026-07-01',
    ]);

    $this->actingAs($user);

    $component = Livewire::test('pages::usage');

    expect($component->instance()->billingCycle['start'])->toBe('2026-06-01');
    expect($component->instance()->billingCycle['end'])->toBe('2026-07-01');
});

test('usage page defaults to current month when no subscription', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => TeamRole::Owner->value]);
    $user->switchTeam($team);
    $user->refresh();

    $this->actingAs($user);

    $component = Livewire::test('pages::usage');

    expect($component->instance()->billingCycle['start'])->toBe(now()->startOfMonth()->toDateString());
    expect($component->instance()->billingCycle['end'])->toBe(now()->endOfMonth()->toDateString());
});

test('usage page shows per-model usage table', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => TeamRole::Owner->value]);
    $user->switchTeam($team);
    $user->refresh();

    $provider = LlmProvider::create([
        'name' => 'TestProvider',
        'slug' => 'test-provider',
        'base_url' => 'https://api.test.com',
        'is_active' => true,
    ]);

    $model = LlmModel::create([
        'llm_provider_id' => $provider->id,
        'name' => 'GPT-4o',
        'external_model_id' => 'gpt-4o',
        'is_active' => true,
    ]);

    UsageLedger::create([
        'team_id' => $team->id,
        'llm_provider_id' => $provider->id,
        'llm_model_id' => $model->id,
        'bucket_date' => now()->toDateString(),
        'bucket_type' => 'day',
        'token_input' => 5000,
        'token_output' => 3000,
        'token_total' => 8000,
        'request_count' => 10,
        'error_count' => 1,
    ]);

    $this->actingAs($user);

    $component = Livewire::test('pages::usage');
    $modelUsage = $component->instance()->modelUsage;

    expect($modelUsage)->toHaveCount(1);
    expect($modelUsage->first()['model_name'])->toBe('GPT-4o');
    expect($modelUsage->first()['token_total'])->toBe(8000);

    $component->assertSee('GPT-4o', false)
        ->assertSee('5,000', false)
        ->assertSee('3,000', false)
        ->assertSee('8,000', false);
});

test('usage page shows daily chart data', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => TeamRole::Owner->value]);
    $user->switchTeam($team);
    $user->refresh();

    $provider = LlmProvider::create([
        'name' => 'TestProvider',
        'slug' => 'test-provider-chart',
        'base_url' => 'https://api.test.com',
        'is_active' => true,
    ]);

    UsageLedger::create([
        'team_id' => $team->id,
        'llm_provider_id' => $provider->id,
        'bucket_date' => now()->toDateString(),
        'bucket_type' => 'day',
        'token_input' => 1000,
        'token_output' => 500,
        'token_total' => 1500,
        'request_count' => 5,
        'error_count' => 0,
    ]);

    $this->actingAs($user);

    $component = Livewire::test('pages::usage');
    $chartData = $component->instance()->chartData;

    expect($chartData['summary']['total_tokens'])->toBe(1500);
    expect($chartData['summary']['total_requests'])->toBe(5);
});

test('usage page only shows data for current team', function () {
    $user = User::factory()->create();
    $teamA = Team::factory()->create();
    $teamB = Team::factory()->create();
    $teamA->members()->attach($user, ['role' => TeamRole::Owner->value]);
    $user->switchTeam($teamA);
    $user->refresh();

    $provider = LlmProvider::create([
        'name' => 'TestProvider',
        'slug' => 'test-provider-scope',
        'base_url' => 'https://api.test.com',
        'is_active' => true,
    ]);

    $modelA = LlmModel::create([
        'llm_provider_id' => $provider->id,
        'name' => 'Team A Model',
        'external_model_id' => 'team-a-model',
        'is_active' => true,
    ]);

    $modelB = LlmModel::create([
        'llm_provider_id' => $provider->id,
        'name' => 'Team B Model',
        'external_model_id' => 'team-b-model',
        'is_active' => true,
    ]);

    UsageLedger::create([
        'team_id' => $teamA->id,
        'llm_provider_id' => $provider->id,
        'llm_model_id' => $modelA->id,
        'bucket_date' => now()->toDateString(),
        'bucket_type' => 'day',
        'token_input' => 100,
        'token_output' => 50,
        'token_total' => 150,
        'request_count' => 1,
        'error_count' => 0,
    ]);

    UsageLedger::create([
        'team_id' => $teamB->id,
        'llm_provider_id' => $provider->id,
        'llm_model_id' => $modelB->id,
        'bucket_date' => now()->toDateString(),
        'bucket_type' => 'day',
        'token_input' => 9999,
        'token_output' => 9999,
        'token_total' => 19998,
        'request_count' => 100,
        'error_count' => 0,
    ]);

    $this->actingAs($user);

    $component = Livewire::test('pages::usage');
    $modelUsage = $component->instance()->modelUsage;

    expect($modelUsage)->toHaveCount(1);
    expect($modelUsage->first()['model_name'])->toBe('Team A Model');

    $component->assertSee('Team A Model', false)
        ->assertDontSee('Team B Model', false);
});
