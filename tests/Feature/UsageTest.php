<?php

use App\Models\LlmModel;
use App\Models\LlmProvider;
use App\Models\UsageLedger;
use App\Models\User;
use Laravel\Cashier\Subscription as CashierSubscription;
use Livewire\Livewire;

test('usage page requires authentication', function () {

    $response = $this->get(route('usage.index'));

    $response->assertRedirect(route('login'));
});

test('usage page can be rendered by authenticated users', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->get(route('usage.index'));

    $response->assertOk();
});

test('usage page shows billing cycle from subscription', function () {
    $user = User::factory()->create();

    $subscription = CashierSubscription::create([
        'user_id' => $user->id,
        'type' => 'default',
        'stripe_id' => 'sub_test123',
        'stripe_status' => 'active',
        'stripe_price' => 'price_pro',
        'quantity' => 1,
    ]);

    // Fix created_at to a known date so the billing cycle assertion is deterministic.
    $subscription->forceFill(['created_at' => '2026-06-15 12:00:00'])->save();

    $this->actingAs($user);

    $component = Livewire::test('pages::usage');

    expect($component->instance()->billingCycle['start'])->toBe('2026-06-01');
    expect($component->instance()->billingCycle['end'])->toBe('2026-06-30');
});

test('usage page defaults to current month when no subscription', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    $component = Livewire::test('pages::usage');

    expect($component->instance()->billingCycle['start'])->toBe(now()->startOfMonth()->toDateString());
    expect($component->instance()->billingCycle['end'])->toBe(now()->endOfMonth()->toDateString());
});

test('usage page shows per-model usage table', function () {
    $user = User::factory()->create();

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
        'user_id' => $user->id,
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

    $provider = LlmProvider::create([
        'name' => 'TestProvider',
        'slug' => 'test-provider-chart',
        'base_url' => 'https://api.test.com',
        'is_active' => true,
    ]);

    UsageLedger::create([
        'user_id' => $user->id,
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

test('usage page only shows data for current user', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    $provider = LlmProvider::create([
        'name' => 'TestProvider',
        'slug' => 'test-provider-scope',
        'base_url' => 'https://api.test.com',
        'is_active' => true,
    ]);

    $modelA = LlmModel::create([
        'llm_provider_id' => $provider->id,
        'name' => 'User A Model',
        'external_model_id' => 'team-a-model',
        'is_active' => true,
    ]);

    $modelB = LlmModel::create([
        'llm_provider_id' => $provider->id,
        'name' => 'User B Model',
        'external_model_id' => 'team-b-model',
        'is_active' => true,
    ]);

    UsageLedger::create([
        'user_id' => $user->id,
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
        'user_id' => $otherUser->id,
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
    expect($modelUsage->first()['model_name'])->toBe('User A Model');

    $component->assertSee('User A Model', false)
        ->assertDontSee('User B Model', false);
});
