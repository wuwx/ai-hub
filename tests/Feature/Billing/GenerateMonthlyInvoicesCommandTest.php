<?php

use App\Models\BillingInvoice;
use App\Models\LlmModel;
use App\Models\LlmProvider;
use App\Models\RequestLog;
use App\Models\User;
use Carbon\CarbonImmutable;

it('generates invoices with billing command for the selected month', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;

    $provider = LlmProvider::create([
        'name' => 'Command Billing Provider',
        'slug' => 'command-billing-provider',
        'adapter_type' => 'openai_compatible',
        'base_url' => 'https://command.example.com',
        'auth_mode' => 'none',
        'is_active' => true,
    ]);

    $model = LlmModel::create([
        'llm_provider_id' => $provider->id,
        'name' => 'Command Model',
        'external_model_id' => 'command-model-v1',
        'pricing' => [
            'input_per_1k_tokens' => 0.02,
            'output_per_1k_tokens' => 0.01,
        ],
        'is_active' => true,
    ]);

    $month = CarbonImmutable::create(2026, 6, 1, 0, 0, 0);

    RequestLog::create([
        'team_id' => $team->id,
        'llm_provider_id' => $provider->id,
        'llm_model_id' => $model->id,
        'protocol' => 'openai',
        'endpoint' => '/v1/chat/completions',
        'http_method' => 'POST',
        'is_streaming' => false,
        'status_code' => 200,
        'token_input' => 1000,
        'token_output' => 2000,
        'token_total' => 3000,
        'requested_at' => $month->copy()->addDays(8),
    ]);

    $this->artisan('billing:generate-monthly-invoices', ['--month' => '2026-06'])
        ->assertSuccessful();

    $invoice = BillingInvoice::query()
        ->where('team_id', $team->id)
        ->whereDate('billing_month', '2026-06-01')
        ->first();

    expect($invoice)->not->toBeNull();
    expect($invoice->total_cents)->toBe(4);
    expect($invoice->status)->toBe('issued');
});
