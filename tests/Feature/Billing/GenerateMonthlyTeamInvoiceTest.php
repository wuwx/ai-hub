<?php

use App\Actions\Billing\GenerateMonthlyTeamInvoice;
use App\Models\LlmModel;
use App\Models\LlmProvider;
use App\Models\RequestLog;
use App\Models\User;
use Carbon\CarbonImmutable;

it('generates a monthly invoice from request usage with per-1k pricing', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;

    $provider = LlmProvider::create([
        'name' => 'Billing Provider',
        'slug' => 'billing-provider',
        'adapter_type' => 'openai_compatible',
        'base_url' => 'https://billing.example.com',
        'auth_mode' => 'none',
        'is_active' => true,
    ]);

    $model = LlmModel::create([
        'llm_provider_id' => $provider->id,
        'name' => 'Commercial Model',
        'external_model_id' => 'commercial-model-v1',
        'pricing' => [
            'input_per_1k_tokens' => 0.01,
            'output_per_1k_tokens' => 0.03,
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
        'token_input' => 2000,
        'token_output' => 1000,
        'token_total' => 3000,
        'requested_at' => $month->copy()->addDays(10),
    ]);

    $invoice = app(GenerateMonthlyTeamInvoice::class)->handle($team, $month);

    expect($invoice->team_id)->toBe($team->id);
    expect($invoice->billing_month->toDateString())->toBe('2026-06-01');
    expect($invoice->currency)->toBe('USD');
    expect($invoice->status)->toBe('issued');
    expect($invoice->total_cents)->toBe(5);

    $item = $invoice->items()->first();

    expect($item)->not->toBeNull();
    expect($item->token_input)->toBe(2000);
    expect($item->token_output)->toBe(1000);
    expect($item->line_subtotal_cents)->toBe(5);
});

it('reuses and refreshes a non-finalized monthly invoice instead of duplicating', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;

    $provider = LlmProvider::create([
        'name' => 'Billing Provider Two',
        'slug' => 'billing-provider-two',
        'adapter_type' => 'openai_compatible',
        'base_url' => 'https://billing-2.example.com',
        'auth_mode' => 'none',
        'is_active' => true,
    ]);

    $model = LlmModel::create([
        'llm_provider_id' => $provider->id,
        'name' => 'Commercial Model Two',
        'external_model_id' => 'commercial-model-v2',
        'pricing' => [
            'input_per_1k_tokens' => 0.02,
            'output_per_1k_tokens' => 0.02,
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
        'token_output' => 1000,
        'token_total' => 2000,
        'requested_at' => $month->copy()->addDays(3),
    ]);

    $firstInvoice = app(GenerateMonthlyTeamInvoice::class)->handle($team, $month);

    RequestLog::create([
        'team_id' => $team->id,
        'llm_provider_id' => $provider->id,
        'llm_model_id' => $model->id,
        'protocol' => 'openai',
        'endpoint' => '/v1/chat/completions',
        'http_method' => 'POST',
        'is_streaming' => false,
        'status_code' => 200,
        'token_input' => 500,
        'token_output' => 500,
        'token_total' => 1000,
        'requested_at' => $month->copy()->addDays(15),
    ]);

    $secondInvoice = app(GenerateMonthlyTeamInvoice::class)->handle($team, $month);

    expect($secondInvoice->id)->toBe($firstInvoice->id);
    expect($secondInvoice->items()->count())->toBe(1);
    expect($secondInvoice->total_cents)->toBe(6);
});
